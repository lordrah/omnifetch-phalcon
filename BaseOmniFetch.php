<?php


namespace OmniFetch;


abstract class BaseOmniFetch implements OmniFetchInterface {
    const DEFAULT_PAGE = 0;
    const DEFAULT_PAGE_SIZE = 20;
    const DEFAULT_COMPARATOR = self::CMP_EQUALS;
    const DEFAULT_CONDITION = self::COND_AND;

    const FILTER_PARAM_FIELD = 'field';
    const FILTER_PARAM_COMPARATOR = 'cmp';
    const FILTER_PARAM_CONDITION = 'cond';
    const FILTER_PARAM_VALUE = 'value';

    const FETCH_PARAMS_FILTERS = 'filters';
    const FETCH_PARAMS_EMBEDS = 'embeds';
    const FETCH_PARAMS_PAGE = 'page';
    const FETCH_PARAMS_PAGE_SIZE = 'page_size';
    const FETCH_PARAMS_ORDER_BY = 'order_by';

    const PAGINATION_NEXT_PAGE = 'next';
    const PAGINATION_PREVIOUS_PAGE = 'previous';
    const PAGINATION_TOTAL_COUNT = 'total_count';
    const PAGINATION_COUNT = 'count';

    const OUTPUT_LIST = 'list';
    const OUTPUT_PAGINATION = 'pagination';

    const CMP_GT = '>';
    const CMP_LT = '<';
    const CMP_EQUALS = '=';
    const CMP_GTE = '>=';
    const CMP_LTE = '<=';
    const CMP_NOT = '!=';
    const CMP_LIKE = 'LIKE';
    const CMP_IS = 'IS';
    const CMP_IS_NOT = 'IS_NOT';

    const COND_AND = 'AND';
    const COND_OR = 'OR';

    public static $filter_comparators = [self::CMP_GT, self::CMP_LT, self::CMP_EQUALS, self::CMP_GTE, self::CMP_LTE, self::CMP_NOT, self::CMP_LIKE, self::CMP_IS, self::CMP_IS_NOT];
    public static $filter_conditions = [self::COND_AND, self::COND_OR];

    /**
     * This contains all the settings that alter how OmniFetch works
     * @var array
     */
    protected $settings = [];

    /**
     * This contains the filtering conditions for the fetching
     * @var array
     */
    protected $filters = [];

    /**
     * This contains the alias of the related data which is to be attached to the main model's data
     * @var array
     */
    protected $embeds = [];

    /**
     * This indicates the page being fetched if pagination is applied. It starts from 0
     * @var int
     */
    protected $page = self::DEFAULT_PAGE;

    /**
     * This indicates the size of a page, in other words, max count of the records fetched
     * @var int
     */
    protected $page_size = self::DEFAULT_PAGE_SIZE;

    /**
     * This determines how data is to be ordered
     * @var string
     */
    protected $order_by = null;

    /**
     * The name of the main model to be fetched
     * @var string
     */
    protected $main_model = null;

    /**
     * This stores the fetched data
     * @var array
     */
    protected $result = [];

    /**
     * This stores the total count for the data fetched
     * @var int
     */
    protected $total_count = 0;

    /**
     * Implements this to get the main model data
     * @param bool $get_single
     */
    protected abstract function fetchMain($get_single = false);

    /**
     * Implements this to fetch and attach an embed to the main model data
     * @param $embed
     */
    protected abstract function fetchEmbed($embed);

    /**
     * Implements this to fetch total count of the main model data
     */
    protected abstract function fetchTotalCount();

    /**
     * @param array $settings - This alters how OmniFetch works
     */
    public function __construct($settings = [])
    {
        $this->settings = $settings;
    }

    /**
     * @return string
     */
    public function getMainModel()
    {
        return $this->main_model;
    }

    /**
     * @param string $main_model
     */
    public function setMainModel($main_model)
    {
        $this->main_model = $main_model;
    }

    /**
     * @return array
     */
    public function getEmbeds()
    {
        return $this->embeds;
    }

    /**
     * @param array $embeds
     */
    public function setEmbeds($embeds)
    {
        if (!is_array($embeds)){
            return;
        }

        foreach ($embeds as $embed){
            $this->embeds[] = trim($embed);
        }
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @param array $filters
     */
    public function setFilters($filters)
    {
        if (!is_array($filters)){
            return;
        }

        $this->filters = [];
        foreach ($filters as $filter){

            if (
                !is_array($filter)
                || empty($filter[self::FILTER_PARAM_FIELD])
                || !is_string($filter[self::FILTER_PARAM_FIELD])
                || empty($filter[self::FILTER_PARAM_VALUE])
            ){
                continue;
            }

            if (is_array($filter[self::FILTER_PARAM_VALUE]) && !in_array(self::FILTER_PARAM_COMPARATOR, [self::CMP_EQUALS, self::CMP_NOT])){
                continue;
            }

            $filter[self::FILTER_PARAM_FIELD] = trim($filter[self::FILTER_PARAM_FIELD]);
            if (!empty($filter[self::FILTER_PARAM_COMPARATOR])) $filter[self::FILTER_PARAM_COMPARATOR] = strtoupper(trim($filter[self::FILTER_PARAM_COMPARATOR]));
            if (!empty($filter[self::FILTER_PARAM_CONDITION])) $filter[self::FILTER_PARAM_CONDITION] = strtoupper(trim($filter[self::FILTER_PARAM_CONDITION]));

            $this->filters[] = [
                self::FILTER_PARAM_FIELD => $filter[self::FILTER_PARAM_FIELD],
                self::FILTER_PARAM_VALUE => $filter[self::FILTER_PARAM_VALUE],
                self::FILTER_PARAM_COMPARATOR => (empty($filter[self::FILTER_PARAM_COMPARATOR]) || !in_array($filter[self::FILTER_PARAM_COMPARATOR], self::$filter_comparators))? self::DEFAULT_COMPARATOR : $filter[self::FILTER_PARAM_COMPARATOR],
                self::FILTER_PARAM_CONDITION => (empty($filter[self::FILTER_PARAM_CONDITION]) ||  !in_array($filter[self::FILTER_PARAM_CONDITION], self::$filter_conditions)) ? self::DEFAULT_CONDITION : $filter[self::FILTER_PARAM_CONDITION],
            ];
        }
    }

    /**
     * @return array
     */
    public function getOrderBy()
    {
        return $this->order_by;
    }

    /**
     * @param string $order_by
     */
    public function setOrderBy($order_by)
    {
        $this->order_by = (preg_match('/^([\w\s,.]*)$/', $order_by)) ? $order_by : null;
    }

    /**
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param int $page
     */
    public function setPage($page)
    {
        $this->page = intval($page);
    }

    /**
     * @return int
     */
    public function getPageSize()
    {
        return $this->page_size;
    }

    /**
     * @param int $page_size
     */
    public function setPageSize($page_size)
    {
        $this->page_size = intval($page_size);
    }

    /**
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * @param array $settings
     */
    public function setSettings($settings)
    {
        if (!is_array($settings)){
            return;
        }
        $this->settings = $settings;
    }

    protected function setParams($params)
    {
        if (!empty($params[self::FETCH_PARAMS_EMBEDS])) { $this->setEmbeds($params[self::FETCH_PARAMS_EMBEDS]); }
        if (!empty($params[self::FETCH_PARAMS_FILTERS])) { $this->setFilters($params[self::FETCH_PARAMS_FILTERS]); }
        if (!empty($params[self::FETCH_PARAMS_PAGE])) { $this->setPage($params[self::FETCH_PARAMS_PAGE]); }
        if (!empty($params[self::FETCH_PARAMS_PAGE_SIZE])) { $this->setPageSize($params[self::FETCH_PARAMS_PAGE_SIZE]); }
        if (!empty($params[self::FETCH_PARAMS_ORDER_BY])) { $this->setOrderBy($params[self::FETCH_PARAMS_ORDER_BY]); }
    }

    /**
     * Fetches records of the `main_model` based on certain params sent in.
     * @param string $main_model - The Main Model which is to be fetch
     * @param array $params - The parameters used to determine what is fetched
     * @param array $settings - This alters how OmniFetch works
     * @return array
     */
    public function getAll($main_model, $params=[], $settings = [])
    {
        $this->setMainModel($main_model);
        $this->setSettings($settings);
        $this->setParams($params);

        $this->fetchMain(false);
        $this->fetchAllEmbeds();
        $this->fetchTotalCount();

        return $this->getOutput(false);
    }

    /**
     * Fetches a single record of the `main_model` based on certain params sent in.
     * @param string $main_model - The Main Model which is to be fetch
     * @param array $params - The parameters used to determine what is fetched
     * @param array $settings - This alters how OmniFetch works
     * @return array|null
     */
    public function getOne($main_model, $params=[], $settings = [])
    {
        $this->setMainModel($main_model);
        $this->setSettings($settings);
        $this->setParams($params);

        $this->fetchMain(true);
        $this->fetchAllEmbeds();

        return $this->getOutput(true);
    }

    /**
     * Fetches and attach all embeds
     */
    protected function fetchAllEmbeds()
    {
        foreach ($this->embeds as $embed){
            $this->fetchEmbed($embed);
        }
    }

    /**
     * Returns a formatted output
     * @param bool $get_single
     * @return array|null
     */
    protected function getOutput($get_single=false)
    {
        $output = [];
        $numeric_arr = (!empty($this->result) && is_array($this->result)) ? array_values($this->result) : null;
        if ($get_single){
            $output = (!empty($numeric_arr) && is_array($numeric_arr)) ? $numeric_arr[0] : null;
        } else {
            $output[self::OUTPUT_LIST] = (!empty($numeric_arr) && is_array($numeric_arr)) ? $numeric_arr : [];
            $output[self::OUTPUT_PAGINATION] = [
                self::PAGINATION_NEXT_PAGE => (($this->page + 1) * $this->page_size) >= $this->total_count ? null : $this->page + 1,
                self::PAGINATION_PREVIOUS_PAGE => ($this->page <= 0) ? null : $this->page - 1,
                self::PAGINATION_TOTAL_COUNT => $this->total_count,
                self::PAGINATION_COUNT => count($output[self::OUTPUT_LIST])
            ];
        }

        return $output;
    }
} 

<?php


namespace OmniFetch;

use Phalcon\Mvc\Model\Relation;

class PhalconOmniFetch extends BaseOmniFetch {

    const SETTING_MAIN_MODEL_PRIMARY_KEY = 'primary_key';

    /**
     * An instance of the main model to be fetched
     * @var \Phalcon\Mvc\Model
     */
    protected $main_model_obj;

    /**
     * Implements this to get the main model data
     * @param bool $get_single
     * @throws \Exception
     */
    protected function fetchMain($get_single = false)
    {
        //checks if the main model's primary key is set in settings
        if (empty($this->settings[self::SETTING_MAIN_MODEL_PRIMARY_KEY])){
            throw new \Exception('No primary key detected for main model');
        }

        //forming the builder
        $this->main_model_obj = new $this->main_model();
        $builder = $this->main_model_obj->getModelsManager()->createBuilder()
            ->columns(["$this->main_model.*"])
            ->from($this->main_model);

        $this->formJoinsUsingFilters($builder);
        $this->formConditions($builder);
        $this->formPagination($builder, $this->page, $this->page_size);
        $this->formOrderBy($builder, $this->order_by);

        //Fetching the data and formatting the result data
        $primary_key = $this->settings[self::SETTING_MAIN_MODEL_PRIMARY_KEY];
        if ($get_single){
            $result_obj = $builder->getQuery()->getSingleResult();
            if (!empty($result_obj)){
                $arr = $result_obj->toArray();
                $this->result[$arr[$primary_key]] = $arr;
            }
        } else {
            $result_set = $builder->getQuery()->execute();
            if (!empty($result_set)){
                foreach ($result_set as $result_obj){
                    $arr = $result_obj->toArray();
                    $this->result[$arr[$primary_key]] = $arr;
                }
            }
        }
    }

    /**
     * Implements this to fetch total count of the main model data
     */
    protected function fetchTotalCount()
    {
        //get total if fetching a list
        $this->main_model_obj = new $this->main_model();
        $total_builder = $this->main_model_obj->getModelsManager()->createBuilder()
            ->columns(['COUNT(1) AS total'])
            ->from($this->main_model);

        $this->formJoinsUsingFilters($total_builder);
        $this->formConditions($total_builder);

        $result_obj = $total_builder->getQuery()->getSingleResult();
        $this->total_count = (empty($result_obj['total'])) ? 0 : $result_obj['total'];
    }

    /**
     * Implements this to fetch and attach an embed to the main model data
     * @param $embed
     * @throws \Exception
     */
    protected function fetchEmbed($embed)
    {
        //checks if the main model's primary key is set in settings
        if (empty($this->settings[self::SETTING_MAIN_MODEL_PRIMARY_KEY])){
            throw new \Exception('No primary key detected for main model');
        }
        //checking if any main model data was fetched
        $primary_keys = array_keys($this->result);
        if (empty($primary_keys)){
            return;
        }

        $primary_key = $this->settings[self::SETTING_MAIN_MODEL_PRIMARY_KEY];
        $columns = ["$this->main_model.$primary_key", "$embed.*"];

        $this->main_model_obj = new $this->main_model();
        $manager = $this->main_model_obj->getModelsManager();

        //checking if $embed is a valid relation of the main model
        $relation = $manager->getRelationByAlias($this->main_model, $embed);
        if (empty($relation)){
            return;
        }

        //forming the builder
        $builder = $manager->createBuilder()
            ->columns($columns)
            ->from($this->main_model);

        $this->formJoins($builder, [$embed]);

        //using the primary keys of the main model to fetch the relevant related data
        $filters[] = [
            self::FILTER_PARAM_FIELD => "$this->main_model.$primary_key",
            self::FILTER_PARAM_VALUE => $primary_keys,
            self::FILTER_PARAM_COMPARATOR => self::CMP_EQUALS,
            self::FILTER_PARAM_CONDITION => self::COND_AND,
        ];
        $this->formConditions($builder, $filters);

        $result_set = $builder->getQuery()->execute();

        $is_array = in_array($relation->getType(), [Relation::HAS_MANY, Relation::HAS_MANY_THROUGH]);

        //Add init data for the embeds in the result
        foreach ($this->result as $key => $item){
            $this->result[$key][$embed] = ($is_array) ? [] : null;
        }

        //Fill in data
        foreach ($result_set as $result_obj){
            if ($is_array){
                $this->result[$result_obj[$primary_key]][$embed][] = $result_obj[lcfirst($embed)]->toArray();
            } else {
                $this->result[$result_obj[$primary_key]][$embed] = $result_obj[lcfirst($embed)]->toArray();
            }
        }
    }

    /**
     * This adds the joins to the builder
     * @param \Phalcon\Mvc\Model\Query\BuilderInterface $builder
     * @param array|null $relation_models
     */
    protected function formJoins(&$builder, $relation_models = null)
    {
        $manager = $this->main_model_obj->getModelsManager();

        foreach ($relation_models as $model){
            //checking if the relation is a valid main model relation
            $relation = $manager->getRelationByAlias($this->main_model, $model);
            if (empty($relation)){
                continue;
            }

            $builder->innerJoin($model);
        }
    }

    /**
     * Adds joins the query builder using the filters provided as inputs
     * @param \Phalcon\Mvc\Model\Query\BuilderInterface $builder
     */
    protected function formJoinsUsingFilters(&$builder)
    {
        $relation_models = [];
        foreach ($this->filters as $filter){
            $field_parts = explode('.', $filter[self::FILTER_PARAM_FIELD]);
            if (count($field_parts) > 1) {
                $relation_models[] = $field_parts[0];
            }
        }

        $this->formJoins($builder, $relation_models);
    }

    /**
     * Adds the where cause to the query builder using filters
     * @param \Phalcon\Mvc\Model\Query\BuilderInterface $builder
     * @param array|null $filters
     */
    protected function formConditions(&$builder, $filters = null)
    {
        if (empty($filters)) { $filters = $this->filters; }
        $param_count = 0;
        foreach ($filters as $filter){
            $param_id = "p_$param_count";

            if (is_array($filter[self::FILTER_PARAM_VALUE])){
                switch ($filter[self::FILTER_PARAM_COMPARATOR]){
                    case self::CMP_NOT:
                        $builder->notInWhere($filter[self::FILTER_PARAM_FIELD], $filter[self::FILTER_PARAM_VALUE]);
                        break;
                    default:
                        $builder->inWhere($filter[self::FILTER_PARAM_FIELD], $filter[self::FILTER_PARAM_VALUE]);
                        break;
                }
            } else {
                //build conditions
                $condition = null;
                switch ($filter[self::FILTER_PARAM_COMPARATOR]){
                    case self::CMP_IS:
                        $condition = "{$filter[self::FILTER_PARAM_FIELD]} IS :$param_id:";
                        break;
                    case self::CMP_IS_NOT:
                        $condition = "NOT {$filter[self::FILTER_PARAM_FIELD]} IS :$param_id:";
                        break;
                    case self::CMP_LIKE:
                        $condition = "{$filter[self::FILTER_PARAM_FIELD]} LIKE :$param_id:";
                        break;
                    default:
                        $condition = "{$filter[self::FILTER_PARAM_FIELD]} {$filter[self::FILTER_PARAM_COMPARATOR]} :$param_id:";
                        break;
                }

                //determine joining operators
                switch ($filter[self::FILTER_PARAM_CONDITION]){
                    case self::COND_OR:
                        $builder->orWhere($condition, [$param_id => $filter[self::FILTER_PARAM_VALUE]]);
                        break;
                    default:
                        $builder->andWhere($condition, [$param_id => $filter[self::FILTER_PARAM_VALUE]]);
                        break;
                }
            }
            $param_count++;
        }
    }

    /**
     * Adds pagination to the builder
     * @param \Phalcon\Mvc\Model\Query\BuilderInterface $builder
     * @param int|null $page
     * @param int|null $page_size
     */
    protected function formPagination(&$builder, $page = null, $page_size = null)
    {
        if (empty($page)) { $page = self::DEFAULT_PAGE; }
        if (empty($page_size)) { $page_size = self::DEFAULT_PAGE_SIZE; }

        $offset = $page * $page_size;
        $builder->limit($page_size, $offset);
    }

    /**
     * Adds the `order by` component of the builder
     * @param \Phalcon\Mvc\Model\Query\BuilderInterface $builder
     * @param string|null $order_by
     */
    protected function formOrderBy(&$builder, $order_by = null)
    {
        if (is_string($order_by)){
            $builder->orderBy($order_by);
        }
    }
} 
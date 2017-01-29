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
     * List of models that have been joined
     * @var array
     */
    protected $join_models = [];

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

        $embed_arr = explode('.', $embed);

        $primary_key = $this->settings[self::SETTING_MAIN_MODEL_PRIMARY_KEY];
        $innermost_embed = $embed_arr[count($embed_arr) - 1];
        $columns = ["$this->main_model.$primary_key", "{$innermost_embed}.*"];

        $this->main_model_obj = new $this->main_model();
        $manager = $this->main_model_obj->getModelsManager();

        //checking if $embed is a valid relation of the main model
        $relation = $this->getValidRelation($embed_arr);
        if (empty($relation)){
            return;
        }

        //forming the builder
        $builder = $manager->createBuilder()
            ->columns($columns)
            ->from($this->main_model);

        $this->join_models = [];
        $this->formJoins($builder, $embed_arr);

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
                $this->result[$result_obj[$primary_key]][$embed][] = $result_obj[lcfirst($innermost_embed)]->toArray();
            } else {
                $this->result[$result_obj[$primary_key]][$embed] = $result_obj[lcfirst($innermost_embed)]->toArray();
            }
        }
    }

    /**
     * Gets the Relation if the embed is a valid relation to the main model. Also handles nested relationships
     * @param array $relation_models - an array representing models in a nested relation in the order of relation, e.g A.B.C -> [A,B,C]
     * @return Relation
     */
    protected function getValidRelation($relation_models)
    {
        /**
         * @var Relation $actual_relation
         */
        $manager = $this->main_model_obj->getModelsManager();
        $_model = $this->main_model;
        $actual_relation = null;

        foreach ($relation_models as $model){
            $relation = $manager->getRelationByAlias($_model, $model);
            if (empty($relation)){
                return null;
            }

            if (empty($actual_relation) || !in_array($actual_relation->getType(), [Relation::HAS_MANY, Relation::HAS_MANY_THROUGH])){
                $actual_relation = $relation;
            }

            $manager = (new $model())->getModelsManager();
            $_model = $model;
        }

        return $actual_relation;
    }

    /**
     * This adds the joins to the builder
     * @param \Phalcon\Mvc\Model\Query\BuilderInterface $builder
     * @param array|null $relation_models - an array representing models in a nested relation in the order of relation, e.g A.B.C -> [A,B,C]
     */
    protected function formJoins(&$builder, $relation_models = null)
    {
        $manager = $this->main_model_obj->getModelsManager();
        $_model = $this->main_model;

        foreach ($relation_models as $model) {
            $relation = $manager->getRelationByAlias($_model, $model);
            if (empty($relation)) {
                return null;
            }

            if (!in_array($model, $this->join_models)) {
                $join_condition = "{$_model}.{$relation->getFields()} = {$model}.{$relation->getReferencedFields()}";
                $builder->innerJoin($model, $join_condition);
            }

            $manager = (new $model())->getModelsManager();
            $_model = $model;
        }
    }

    /**
     * Adds joins the query builder using the filters provided as inputs
     * @param \Phalcon\Mvc\Model\Query\BuilderInterface $builder
     */
    protected function formJoinsUsingFilters(&$builder)
    {
        $this->join_models = [];
        foreach ($this->filters as $filter){
            $field_parts = explode('.', $filter[self::FILTER_PARAM_FIELD]);
            if (count($field_parts) > 1) {
                unset($field_parts[count($field_parts) - 1]);
                $this->formJoins($builder, $field_parts);
            }
        }
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

            $actual_field = $filter[self::FILTER_PARAM_FIELD];
            $actual_field_arr = explode('.', $filter[self::FILTER_PARAM_FIELD]);
            $count_parts = count($actual_field_arr);
            if ($count_parts > 2){
                $actual_field = $actual_field_arr[$count_parts - 2] . '.' . $actual_field_arr[$count_parts - 1];
            }

            if (is_array($filter[self::FILTER_PARAM_VALUE])){
                switch ($filter[self::FILTER_PARAM_COMPARATOR]){
                    case self::CMP_NOT:
                        $builder->notInWhere($actual_field, $filter[self::FILTER_PARAM_VALUE]);
                        break;
                    default:
                        $builder->inWhere($actual_field, $filter[self::FILTER_PARAM_VALUE]);
                        break;
                }
            } else {
                //build conditions
                $condition = null;
                switch ($filter[self::FILTER_PARAM_COMPARATOR]){
                    case self::CMP_IS:
                        $condition = "{$actual_field} IS :$param_id:";
                        break;
                    case self::CMP_IS_NOT:
                        $condition = "NOT {$actual_field} IS :$param_id:";
                        break;
                    case self::CMP_LIKE:
                        $condition = "{$actual_field} LIKE :$param_id:";
                        break;
                    default:
                        $condition = "{$actual_field} {$filter[self::FILTER_PARAM_COMPARATOR]} :$param_id:";
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
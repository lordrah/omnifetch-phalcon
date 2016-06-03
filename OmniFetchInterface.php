<?php


namespace OmniFetch;


interface OmniFetchInterface {
    /**
     * @param array $settings - This alters how OmniFetch works
     */
    public function __construct($settings=[]);

    /**
     * Fetches records of the `main_model` based on certain params sent in.
     * @param string $main_model - The Main Model which is to be fetch
     * @param array $params - The parameters used to determine what is fetched
     * @param array $settings - This alters how OmniFetch works
     * @return array
     */
    public function getAll($main_model, $params=[], $settings=[]);

    /**
     * Fetches a single record of the `main_model` based on certain params sent in.
     * @param string $main_model - The Main Model which is to be fetch
     * @param array $params - The parameters used to determine what is fetched
     * @param array $settings - This alters how OmniFetch works
     * @return array|null
     */
    public function getOne($main_model, $params=[], $settings=[]);
}
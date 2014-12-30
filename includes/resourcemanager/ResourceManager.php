<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 29.12.14
 * Time: 22:43
 */

/**
 * Class ResourceManager
 */
class ResourceManager{
    /**
     * @var ResourceManager
     */
    private static $instance = null;
    /**
     * @var array
     */
    private $config = array();
    /**
     * @param array $config
     */
    protected function __construct(array $config) {
        if(is_array($config)) {
            $this->config = $config;
        }
    }

    /**
     * @param array $config
     * @throws ResourceManagerException
     */
    public static function initialize(array $config) {
        if(!isset(self::$instance)) {
            if(is_array($config)) {
                self::$instance = new ResourceManager($config);
            } else {
                throw new ResourceManagerException("No resourcemanager configuration!");
            }
        }
    }

    /**
     * @return ResourceManager
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new ResourceManager(array());
        }
        return self::$instance;
    }
} 
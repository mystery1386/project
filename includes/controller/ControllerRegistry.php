<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 29.12.14
 * Time: 22:16
 */
require_once "ControllerRegistryException.php";

/**
 * Class ControllerRegistry
 */
class ControllerRegistry {
    /**
     * @var null|ControllerRegistry
     */
    private static $instance = null;
    /**
     * @var null|array
     */
    private $controller = null;

    /**
     * @return ControllerRegistry|null
     */
    public static function getInstance() {
        if (!isset(self::$instance)) {
            self::$instance = new ControllerRegistry();
            self::$instance->init();
        }
        return self::$instance;
    }

    /**
     * @author mark
     */
    protected function init() {
        if (!isset($this->controller) || empty($this->controller)) {
            $this->controller = Config::getInstance()->find(array("controller", "instances"));
        }
    }

    /**
     * @param $alias
     * @return AbstractController
     * @throws ControllerRegistryException
     */
    public function get($alias) {
        if (!isset($this->controller[$alias])) {
            throw new ControllerRegistryException("Controller with alias {$alias} could not be found!");
        }
        return $this->controller[$alias];
    }

} 
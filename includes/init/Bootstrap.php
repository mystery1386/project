<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 07.12.14
 * Time: 00:45
 */
require_once "BootstrapInterface.php";
require_once "BootstrapException.php";

/**
 * Class Bootstrap
 */
class Bootstrap implements BootstrapInterface{

    /**
     * @param array $config
     * @throws BootstrapException
     */
    public static function initialize(array $config) {
        if(is_array($config) && isset($config['bootstrap'])) {
            usort($config["bootstrap"], function($a, $b) {
                $pA = (isset($a["priority"]) && $a["priority"] === true) ? 1 : 0;
                $pB = (isset($b["priority"]) && $b["priority"] === true) ? 1 : 0;
                if ($pA === $pB) {
                    return 0;
                }
                return ($pA < $pB) ? +1 : -1;
            });
            foreach($config['bootstrap'] as $element) {
                if(isset($element['file'])) {
                    require_once($element['file']);
                    $className = basename($element['file'], '.php');
                    if(array_search('BootstrapInterface', class_implements($className)) !== false) {
                        if(isset($element['config']) && is_array($config[$element['config']])) {
                            $classConfig = $config[$element['config']];
                        } else {
                            $classConfig = array();
                        }
                        /*
                         * @var BootstrapInterface $className
                         */
                        $className::initialize($classConfig);
                    } else {
                        throw new BootstrapException("{$className} does not implement BootstrapInterface!");
                    }
                }
            }
        } else {
            throw new BootstrapException("No bootstrap config found!!!");
        }
    }
} 
<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 06.12.14
 * Time: 22:21
 */

include_once dirname(__DIR__) . "/includes/system/commons/base.php";

$baseConfig = dirname(__DIR__) . "/configs/config.base.yml";
Config::getInstance()->load($baseConfig);
Bootstrap::initialize(Config::getInstance()->getData());
Config::getInstance()->find(array("controller", "instances"));
print_r("finished");

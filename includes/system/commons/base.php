<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 06.12.14
 * Time: 22:24
 */

date_default_timezone_set('Europe/Berlin');

$includePath = ini_get('include_path');
//Die includes und die Anwendung finden, wir befinden uns in ANWENDUNG/incudes/system/genericsearch
$includesPath = dirname(dirname(__DIR__));
$includePath .= PATH_SEPARATOR;
$includePath .= $includesPath;
$applicationPath = dirname($includesPath);
$includePath .= PATH_SEPARATOR;
$includePath .= $applicationPath;
set_include_path($includePath);

//Diese brauchen wir immer!
require_once('includes/globals/Environment.php');
require_once('includes/globals/Config.php');
require_once('includes/init/Bootstrap.php');
require_once('includes/controller/ControllerRegistry.php');
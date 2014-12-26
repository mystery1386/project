<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 06.12.14
 * Time: 20:02
 */
require_once "AbstractFormat.php";

/**
 * Class FormatYml
 */
class FormatYml extends AbstractFormat{

    /**
     * @var AbstractFormat
     */
    private static $instance = null;

    /**
     * @param string $filename
     * @return array|void
     * @throws Exception
     */
    public function loadFile($filename) {
        $yamlExtension = dirname(__DIR__) . "/extensions/spyc/Spyc.php";
        if(file_exists($filename)) {
            if(function_exists('yaml_parse_file')) {
                try {
                    $data = yaml_parse_file($filename);
                } catch(Exception $exception) {
                    throw new Exception($exception->getMessage()."[{$filename}]");
                }
            } else if (file_exists($yamlExtension)) {
                require_once $yamlExtension;
                $data = Spyc::YAMLLoad($filename);
            } else {
                throw new FormatException("Yaml extension is not installed!");
            }
            if($data === false) {
                throw new FormatException("Data for configfile {$filename} invalid. Please Check.");
            }
            return $data;
        }
        return array();
    }

    /**
     * @return AbstractFormat|FormatYml
     */
    public static function getInstance() {
        if (!isset(self::$instance)) {
            self::$instance = new FormatYml();
        }
        return self::$instance;
    }
} 
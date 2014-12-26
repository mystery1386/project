<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 06.12.14
 * Time: 20:35
 */

class FormatFactory {

    /**
     * @param $file
     * @return mixed
     * @throws Exception
     */
    public static function load($file) {
        if (!file_exists($file)) {
            throw new FormatFactoryException("file {$file} not found");
        }
        $info = pathinfo($file);
        if (!isset($info["extension"])) {
            throw new FormatFactoryException("extension could not be determined");
        }
        $extension = ucfirst($info["extension"]);
        /*
         * @var AbstractFormat $formatFile
         */
        $formatFile = __DIR__ . "/Format{$extension}.php";
        $className = "Format{$extension}";
        if (!file_exists($formatFile)) {
            throw new FormatFactoryException("format file for extension {$extension} could not be found");
        }
        require_once $formatFile;

        return $className::getInstance()->loadFile($file);
    }
}

class FormatFactoryException extends Exception{}
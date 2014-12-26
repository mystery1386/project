<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 06.12.14
 * Time: 20:11
 */

abstract class AbstractFormat {

    /**
     * @param $filename
     */
    abstract function loadFile($filename);
}

/**
 * Class FormatException
 */
class FormatException extends Exception{}
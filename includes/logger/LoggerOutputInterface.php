<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 07.12.14
 * Time: 01:47
 */

interface LoggerOutputInterface {
    public function out(array $data, $level);
} 
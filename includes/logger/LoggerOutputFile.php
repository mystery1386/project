<?php
/**
 * @created 02.01.2013 11:16:05
 * @author mregner
 * @version $Id$
 */
require_once('LoggerOutputInterface.php');
class LoggerOutputFile implements LoggerOutputInterface {
    /**
     * @var array
     */
    protected $config = array();
    /**
     * @var string
     */
    protected $logfile = null;
    /**
     * @param array $config
     * @author mregner
     */
    public function __construct(array $config) {
        $this->config = $config;
    }
    /**
     * @author mregner
     */
    protected function getLogfile() {
        if(!isset($this->logfile)) {
            $this->logfile = isset($this->config['file']) ? $this->config['file']:'/tmp/default.log';
            $logdir = dirname($this->logfile);
            file_exists($logdir) || @mkdir($logdir);
        }
        return $this->logfile;
    }
    /**
     * (non-PHPdoc)
     * @see LoggerOutputInterface::out()
     */
    public function out(array $data, $level) {
        if(!empty($data['message']) && $data['message'] != '.') {
            $line = "{$data['message']}\n\n";
        } else {
            $line = ".";
        }
        @file_put_contents($this->getLogfile(), $line, FILE_APPEND | FILE_USE_INCLUDE_PATH);
    }
}
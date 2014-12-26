<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 24.12.14
 * Time: 22:19
 */

class RequestWrapper {
    /**
     * @var array
     */
    protected $headers = null;
    /**
     * @var string
     */
    protected $rawPostData = "";
    /**
     * @var array
     */
    protected $uploadedFiles = array();
    /**
     * @var array
     */
    protected $data = array();
    /**
     * @param array $data
     */
    public function __construct(array $data) {
        $this->setData($data);
    }
    /**
     * @return array
     */
    public function getHeaders() {
        if(!isset($this->headers)) {
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $this->headers[str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))))] = $value;
                }
            }
        }
        return $this->headers;
    }
    /**
     * @param string $offset
     * @return boolean
     */
    public function exists($offset) {
        if($offset == 'request') {
            return true;
        } else {
            return isset($this->data[$offset]);
        }
    }
    /**
     * @param string $offset
     * @return mixed
     */
    public function get($offset) {
        if($offset == 'request') {
            return $this->data;
        } else {
            return $this->data[$offset];
        }
    }
    /**
     * @param $data
     */
    public function setData($data) {
        if (isset($data["HTTP_RAW_POST_DATA"])) {
            $this->rawPostData = $data["HTTP_RAW_POST_DATA"];
            unset($data["HTTP_RAW_POST_DATA"]);
        }
        if (isset($data["HTTP_POST_FILES"])) {
            $this->uploadedFiles = $data["HTTP_POST_FILES"];
            unset($data["HTTP_POST_FILES"]);
            // *Zusätzlich* die hochgeladene Dateien unter dem Namen des Feldes in die Requestdaten mergen
            foreach ($this->uploadedFiles as $filename => $fileinfo) {
                $data[$filename] = $fileinfo;
            }
        }
        $this->data = $data;
    }
    /**
     * @return string
     */
    public function getRawPostData() {
        return $this->rawPostData;
    }
    /**
     * @return array
     */
    public function getUploadedFiles() {
        return $this->uploadedFiles;
    }
} 
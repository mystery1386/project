
<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 11.10.14
 * Time: 13:41
 */
/**
 * Class BadRequestException
 */
class BadRequestException extends Exception {}
/**
 * Class Request
 */
class Request {
    /**
     * @throws BadRequestException
     * @return array
     */
    public static function getData() {
        $result = array();
        if (is_array($_GET)) {
            $result = array_merge($result, $_GET);
        }
        if (is_array($_POST)) {
            $result = array_merge($result, $_POST);
        }
        global $HTTP_RAW_POST_DATA;
        if (isset($HTTP_RAW_POST_DATA) && !empty($HTTP_RAW_POST_DATA)) {
            $result["HTTP_RAW_POST_DATA"] = $HTTP_RAW_POST_DATA;
        }
        if (is_array($_FILES) && count($_FILES) > 0) {
            $result["HTTP_POST_FILES"] = $_FILES;
        }
        return $result;
    }
    /**
     * @param string $key
     * @return mixed
     */
    public static function get($key) {
        $data = self::getData();
        if(isset($data[$key])) {
            return $data[$key];
        }
        return null;
    }
    /**
     * @param $key
     * @return bool
     */
    public static function has($key) {
        $data = self::getData();
        return isset($data[$key]);
    }
    /**
     * @return array
     */
    public static function keys() {
        $data = self::getData();
        return array_keys($data);
    }
}
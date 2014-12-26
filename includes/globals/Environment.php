<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 06.12.14
 * Time: 20:56
 */
require_once('Server.php');

/**
 * Class Environment
 */
class Environment {
    /**
     * @var array
     */
    protected static $PATHS = array();

    /**
     * @param string $key
     * @param mixed $value
     */
    public static function set($key, $value) {
        $_ENV[$key] = $value;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public static function get($key) {
        if(isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        return null;
    }

    /**
     * @param $key
     * @return bool
     */
    public static function has($key) {
        return isset($_ENV[$key]);
    }


    /**
     * @return string
     */
    public static function getIncludePath() {
        if(!isset(self::$PATHS['_INCLUDE_PATH'])) {
            /**
             * Finde den Basispfad fuer diese includes.
             */
            self::$PATHS['_INCLUDE_PATH'] = dirname(dirname(__DIR__)) . "/";
        }
        return self::$PATHS['_INCLUDE_PATH'];
    }

    /**
     * @return string
     */
    public static function getApplicationPath() {
        if(!isset(self::$PATHS['_APPLICATION_PATH'])) {
            //On a loosely configured apache the DOCUMENT_ROOT variable could be misleading.
            self::$PATHS['_APPLICATION_PATH'] = str_replace(Server::get('SCRIPT_NAME'), "/", Server::get('SCRIPT_FILENAME'));
            (self::$PATHS['_APPLICATION_PATH'] === '/') && (self::$PATHS['_APPLICATION_PATH'] = self::getRootPath() . "site/");
        }
        return self::$PATHS['_APPLICATION_PATH'];
    }

    /**
     * @return string
     */
    public static function getAssetPath() {
        if (!isset(self::$PATHS["_ASSET_PATH"])) {
            self::$PATHS["_ASSET_PATH"] = self::getApplicationPath() . "assets/";
        }

        return self::$PATHS["_ASSET_PATH"];
    }

    /**
     * @return mixed
     */
    public static function getProxyPath() {
        if(!isset(self::$PATHS['_PROXY_PATH'])) {
            if(Server::has('HTTP_X_FORWARDED_PATH')) {
                self::$PATHS['_PROXY_PATH'] = Server::get('HTTP_X_FORWARDED_PATH');
            } else {
                self::$PATHS['_PROXY_PATH'] = '';
            }
        }
        return self::$PATHS['_PROXY_PATH'];
    }

    /**
     * @return string
     */
    public static function getRootPath() {
        if(!isset(self::$PATHS['_ROOT_PATH'])) {
            //On a loosely configured apache the DOCUMENT_ROOT variable could be misleading.
            self::$PATHS['_ROOT_PATH'] = dirname(dirname(__DIR__)) . "/";
            #self::$PATHS['_ROOT_PATH'] = dirname(dirname(dirname(dirname(__DIR__)))) . "/";
        }
        return self::$PATHS['_ROOT_PATH'];
    }

    /**
     * @param $base_path
     */
    public static function setTempBasePath($base_path) {
        self::$PATHS['_TMP_ROOT_PATH'] = $base_path;
    }

    /**
     * @return string
     */
    public static function getTempBasePath() {
        if(!isset(self::$PATHS['_TMP_ROOT_PATH'])) {
            self::$PATHS['_TMP_ROOT_PATH'] = self::getRootPath() . "tmp/";
        }
        return self::$PATHS['_TMP_ROOT_PATH'];
    }

    /**
     * @param string $subpath
     * @return string
     */
    public static function getTempPath($subpath=null) {
        $tempBasePath = self::getTempBasePath();
        if(isset($subpath)) {
            $tmpPath = "{$tempBasePath}{$subpath}/";
            if(!file_exists($tmpPath)) {
                @mkdir($tmpPath, 0777, true);
            }
            return $tmpPath;
        }
        return $tempBasePath;
    }

    /**
     * @param string $file
     * @param string  $subpath
     * @param string $extension
     * @return string
     */
    public static function getTempFile($file, $subpath=null, $extension=null) {
        $tempPath = self::getTempPath($subpath);
        $tmpfile = md5($file) . "_" . basename($file);
        isset($extension) && ($tmpfile .= ".{$extension}");
        return "{$tempPath}{$tmpfile}";
    }
} 
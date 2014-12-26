<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 11.10.14
 * Time: 13:28
 */
class Server {
    /**
     *
     * @param $key
     * @return null|string
     */
    public static function get($key) {
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        return null;
    }

    /**
     * @param $key
     * @return bool
     */
    public static function has($key) {
        return isset($_SERVER[$key]);
    }

    /**
     * @return null|string
     */
    public static function getScriptName() {
        return self::get("SCRIPT_NAME");
    }

    /**
     * @return null|string
     */
    public static function getServerName() {
        return self::get("SERVER_NAME");
    }

    /**
     * @return null|string
     */
    public static function getServerPort() {
        return self::get("SERVER_PORT");
    }

    /**
     * @return mixed
     */
    public static function getRemoteAddr() {
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            list($remoteAddr) = explode(",",$_SERVER['HTTP_X_FORWARDED_FOR']);
            return $remoteAddr;
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    /**
     * @return bool
     */
    public static function isHttps() {
        if(Server::has('HTTPS')) {
            return true;
        } else if(Server::has('HTTP_X_FORWARDED_PROTO')) {
            return 'https' === strtolower(Server::get('HTTP_X_FORWARDED_PROTO'));
        }
        return false;
    }

    /**
     * Parsed den HTTP_REFERER so vorhanden, bereitet das auf
     * und gibt die Daten als Array zurueck.
     *
     * @return array
     */
    public static function getReferer() {
        $referer = array();
        if(isset($_SERVER['HTTP_REFERER'])) {
            $referer = parse_url($_SERVER['HTTP_REFERER']);
            if(isset($referer['query'])) {
                $referer['params'] = array();
                $pairs = explode('&', $referer['query']);
                foreach($pairs as $pair) {
                    list($key, $value) = explode("=", $pair);
                    $referer['params'][$key] = $value;
                }
            }
        }
        return $referer;
    }

    /**
     * Gibt eine Liste der vom Browser preferierten Sprachen zurueck.
     *
     * @return array
     */
    public static function getLanguages() {
        $languages = array();

        if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches);
            if (!empty($matches[1])) {
                //Erzeuge eine Liste der Sprachen und Wertung "en" => 0.8
                $data = array_combine($matches[1], $matches[4]);

                // set default to 1 for any without q factor
                foreach ($data as $lang => $val) {
                    if ($val === '') $data[$lang] = 1;
                }

                // sort list based on value
                arsort($data, SORT_NUMERIC);
                $languages = array_keys($data);
            }
        }

        return $languages;
    }
} 
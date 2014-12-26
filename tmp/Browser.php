<?php
/**
 * Created on 30.03.2005 09:27:33
 * @author mregner
 * @version $Id$
 *
 * Aufruf:
 * $browser = new Browser("/tmp/mycookie.txt");
 * $browser->POST("URL", $POSTPARAMS);
 */
//START_CLASS
if (!class_exists("Browser")) {
    class Browser
    {
        const PROXY_TYPE_SOCKS4 = 4;
        const PROXY_TYPE_SOCKS5 = 5;
        private $cookiejar;
        private $user_agent = "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)";
        private $curl;
        private $referer = '';
        private $min_delay = 0;
        private $max_delay = 0;
        private $last_title = '';
        private $proxy = '';
        private $proxyType = self::PROXY_TYPE_SOCKS4;
        private $timeout = 30;
        private $info = array();
        private $effectiveUrl = '';
        private $port;
        private $http_code = "";
        private $followRedirect = false;
        private $headerOnly = false;
        private $includeHeader = false;
        private $userAuthData = null;
        private $error = '';
        private $errorno = 0;
        private $header = array();
        private $timeInfo = array();
        /**
         * @var array
         */
        private $responseHeader = array();
        private $lastURL = '';
        private $encoding = null;
        /**
         * @var array
         */
        private $cookies = array();

        /**
         * @param string $url
         * @return int
         */
        public static function CHECK($url)
        {
            $handle = curl_init($url);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
            curl_exec($handle);
            $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);
            return $httpCode;
        }

        /**
         * @param string $cookiej Name und Pfad der Cookiedatei.
         */
        public function __construct($cookiej)
        {
            if (!function_exists('curl_init')) {
                @ dl('curl.so');
            }
            $this->cookiejar = $cookiej;
        }

        /**
         * @return void
         */
        public function __destruct()
        {
            if ($this->cookiejar && file_exists($this->cookiejar)) {
                unlink($this->cookiejar);
            }
        }

        /**
         * @param string $encoding
         */
        public function setEncoding($encoding)
        {
            $this->encoding = $encoding;
        }

        public function getLastURL()
        {
            return $this->lastURL;
        }

        private $omitSSLVerification = false;

        /**
         * @param string $cookiejar
         * @author mregner
         */
        public function setCookiejar($cookiejar)
        {
            $this->cookiejar = $cookiejar;
        }

        /**
         * @return string
         * @author mregner
         */
        public function getCookiejar()
        {
            return $this->cookiejar;
        }

        /**
         * @author mregner
         */
        public function getCookiesFromJar()
        {
            $cookies = array();
            if (file_exists($this->cookiejar)) {
                $lines = file($this->cookiejar);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line) && $line[0] !== '#') {
                        $tokens = explode("\t", $line);
                        $tokens = array_map('trim', $tokens);
                        if (isset($tokens[5])) {
                            $cookies[] = array(
                                'key' => $tokens[5],
                                'value' => $tokens[6],
                                'expiration' => $tokens[4],
                                'domain' => $tokens[0],
                                'path' => $tokens[2]
                            );
                        }
                    }
                }
            }
            return $cookies;
        }

        /**
         * @return int
         * @author mregner
         */
        public function getErrorno()
        {
            return $this->errorno;
        }

        /**
         * @return string
         * @author mregner
         */
        public function getHttpCode()
        {
            return $this->http_code;
        }

        /**
         * @return array
         * @author mregner
         */
        public function getInfo()
        {
            return $this->info;
        }

        /**
         * @return string
         * @author mregner
         */
        public function getLastTitle()
        {
            return $this->last_title;
        }

        /**
         * @param $timeout
         * @author mregner
         */
        public function setTimeout($timeout)
        {
            $this->timeout = $timeout;
        }

        /**
         * @return int
         * @author mregner
         */
        public function getTimeout()
        {
            return $this->timeout;
        }

        /**
         * @param $user_agent
         * @author mregner
         */
        public function setUserAgent($user_agent)
        {
            $this->user_agent = $user_agent;
        }

        /**
         * @return string
         * @author mregner
         */
        public function getUserAgent()
        {
            return $this->user_agent;
        }

        /**
         * @param array $header
         * @author mregner
         */
        public function setHeader(array $header)
        {
            $this->header = $header;
        }

        /**
         * @return array
         * @author mregner
         */
        public function getHeader()
        {
            return $this->header;
        }

        /**
         * @return array
         * @author mregner
         */
        public function getResponseHeader()
        {
            return $this->responseHeader;
        }

        /**
         * @param string $key
         * @param string $value
         * @author mregner
         */
        public function addCookie($key, $value)
        {
            $this->cookies[] = "{$key}={$value}";
        }

        /**
         * @return array
         * @author mregner
         */
        public function getCookies()
        {
            return $this->cookies;
        }

        /**
         * @return array
         */
        public function getErrorData()
        {
            return array('errorno' => $this->errorno, 'error' => $this->error,);
        }

        /**
         * @param string $proxy
         * @param integer $type
         * @author mregner
         */
        public function setProxy($proxy, $type = self::PROXY_TYPE_SOCKS4)
        {
            $this->proxy = $proxy;
            if ($type == self::PROXY_TYPE_SOCKS4 || $type == self::PROXY_TYPE_SOCKS5) $this->proxyType = $type;
        }

        /**
         * @param integer $min
         * @param integer $max
         * @author mregner
         */
        public function setDelay($min, $max)
        {
            $this->min_delay = min($min, $max);
            $this->max_delay = max($min, $max);
        }

        /**
         * @param string $username
         * @param string $password
         * @author mregner
         */
        public function setUserAuthData($username, $password)
        {
            $this->userAuthData = "{$username}:{$password}";
        }

        /**
         * @param bool $value
         * @author mregner
         */
        public function setOmitSSLVerification($value)
        {
            if (is_bool($value)) {
                $this->omitSSLVerification = $value;
            }
        }

        /**
         * @param bool $value
         * @author mregner
         */
        public function setFollowRedirect($value)
        {
            if (is_bool($value)) {
                $this->followRedirect = $value;
            }
        }

        /**
         * @param bool $value
         * @author mregner
         */
        public function setHeaderOnly($value)
        {
            if (is_bool($value)) {
                $this->headerOnly = $value;
            }
        }

        /**
         * @param bool $value
         * @author mregner
         */
        public function setIncludeHeader($value)
        {
            if (is_bool($value)) {
                $this->includeHeader = $value;
            }
        }

        /**
         * @param string $referer
         * @author mregner
         */
        public function setReferer($referer)
        {
            $this->referer = $referer;
        }

        /**
         * @return string
         * @author mregner
         */
        public function getEffectiveUrl()
        {
            return $this->effectiveUrl;
        }

        /**
         * @return integer
         * @author mregner
         */
        public function getErrno()
        {
            return $this->errorno;
        }

        /**
         * @return string
         * @author mregner
         */
        public function getError()
        {
            return $this->error;
        }

        /**
         * @author mregner
         */
        protected function delay()
        {
            if ($this->max_delay > 0) {
                $dl = rand($this->min_delay, $this->max_delay);
                sleep($dl);
            }
        }

        /**
         * @author mregner
         */
        protected function extractTimeinfo()
        {
            if (is_resource($this->curl)) {
                $this->timeInfo['total_time'] = curl_getinfo($this->curl, CURLINFO_TOTAL_TIME);
                $this->timeInfo['namelookup_time'] = curl_getinfo($this->curl, CURLINFO_NAMELOOKUP_TIME);
                $this->timeInfo['connect_time'] = curl_getinfo($this->curl, CURLINFO_CONNECT_TIME);
                $this->timeInfo['file_time'] = curl_getinfo($this->curl, CURLINFO_FILETIME);
                $this->timeInfo['pretransfer_time'] = curl_getinfo($this->curl, CURLINFO_PRETRANSFER_TIME);
                $this->timeInfo['starttransfer_time'] = curl_getinfo($this->curl, CURLINFO_STARTTRANSFER_TIME);
                $this->timeInfo['redirect_time'] = curl_getinfo($this->curl, CURLINFO_REDIRECT_TIME);
            }
        }

        /**
         * @return array
         * @author mregner
         */
        public function getTimeinfo()
        {
            return $this->timeInfo;
        }

        /**
         * Initialize the curl stuff.
         *
         * @author mregner
         */
        protected function init()
        {
            $this->curl = curl_init();
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            // this line makes it work under https
            curl_setopt($this->curl, CURLOPT_USERAGENT, $this->user_agent);
            curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($this->curl, CURLOPT_COOKIEJAR, $this->cookiejar);
            curl_setopt($this->curl, CURLOPT_COOKIEFILE, $this->cookiejar);
            curl_setopt($this->curl, CURLOPT_REFERER, $this->referer);
            if (isset($this->encoding)) {
                curl_setopt($this->curl, CURLOPT_ENCODING, $this->encoding);
            }
            if ($this->omitSSLVerification) {
                curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
            }
            if (isset($this->userAuthData)) {
                curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($this->curl, CURLOPT_USERPWD, $this->userAuthData);
            }
            if ($this->proxy != '') {
                curl_setopt($this->curl, CURLOPT_PROXY, $this->proxy);
                if ($this->proxyType == self::PROXY_TYPE_SOCKS4) {
                    curl_setopt($this->curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
                } else if ($this->proxyType == self::PROXY_TYPE_SOCKS5) {
                    curl_setopt($this->curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                }
            }
            $this->errorno = false;
            $this->error = false;
        }

        /**
         * @param $params *
         * @return int
         * @author markb
         */
        private function usePostParams($params)
        {
            foreach ($params as $value) {
                if (is_array($value)) {
                    $result = $this->usePostParams($value);
                    if ($result === true) return true;
                } else if (preg_match("~^([@])~", $value)) {
                    return true;
                }
            }
            return false;
        }


        /**
         * @param string $url
         * @param string $params
         * @param null   $host
         * @param int    $timeout
         *
         * @return string
         */
        public function POST($url, $params = "", $host = null, $timeout = 0)
        {
            $this->delay();
            $this->init();
            if (is_array($params)) {
                if ($this->usePostParams($params)) {
                    $postData = $params;
                } else {
                    $postData = $this->toQueryString($params);
                }
            } else {
                $postData = $params;
            }
            curl_setopt($this->curl, CURLOPT_POST, 1);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($this->curl, CURLOPT_URL, $url);
            if (isset ($this->port)) {
                curl_setopt($this->curl, CURLOPT_PORT, $this->port);
            }
            if (isset($host)) {
                $this->header[] = 'Host: ' . $host;
            }
            if (!empty($this->header)) {
                curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->header);
            }
            if ($timeout > 0) {
                curl_setopt($this->curl, CURLOPT_TIMEOUT, $timeout);
            }
            if ($this->followRedirect != null) {
                curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, $this->followRedirect);
            }
            if (!empty($this->cookies)) {
                curl_setopt($this->curl, CURLOPT_COOKIE, implode(";", $this->cookies));
            }
            if ($this->includeHeader || $this->headerOnly) {
                curl_setopt($this->curl, CURLOPT_HEADER, true);
            }
            $response = curl_exec($this->curl);
            if ($this->includeHeader) {
                list($reponseHeader, $response) = preg_split("~(\\r\\n){2}~", $response, 2);
                $this->responseHeader = $this->parseHeaders($reponseHeader);
                $response = trim($response);
            }
            if (!$response) {
                $this->errorno = curl_errno($this->curl);
                $this->error = curl_error($this->curl);
            }
            $this->referer = curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL);
            $this->http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            //$this->info = curl_getinfo($this->curl);
            $this->effectiveUrl = curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL);
            $this->extractTimeinfo();
            curl_close($this->curl);
            return $response;
        }

        /**
         * @param string $url
         * @param array $params
         * @param string $host
         * @param integer $timeout
         * @return string
         * @author mregner
         */
        public function GET($url, array $params = null, $host = null, $timeout = 0)
        {
            $this->delay();
            $this->init();
            if (isset($params) && !empty($params)) {
                $queryString = $this->toQueryString($params);
                if (preg_match("~\?~", $url)) {
                    $url .= "&" . $queryString;
                } else {
                    $url .= "?" . $queryString;
                }
            }
            $this->lastURL = $url;
            curl_setopt($this->curl, CURLOPT_URL, $url);
            if (isset ($this->port)) {
                curl_setopt($this->curl, CURLOPT_PORT, $this->port);
            }
            if (isset($host)) {
                $this->header[] = 'Host: ' . $host;
            }
            if (!empty($this->header)) {
                curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->header);
            }
            if ($timeout > 0) {
                curl_setopt($this->curl, CURLOPT_TIMEOUT, $timeout);
            }
            if ($this->followRedirect != null) {
                curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, $this->followRedirect);
            }
            if (!empty($this->cookies)) {
                curl_setopt($this->curl, CURLOPT_COOKIE, implode(";", $this->cookies));
            }
            if ($this->includeHeader || $this->headerOnly) {
                curl_setopt($this->curl, CURLOPT_HEADER, true);
            }
            if ($this->headerOnly) {
                curl_setopt($this->curl, CURLOPT_NOBODY, true);
            }
            $response = curl_exec($this->curl);
            if ($this->includeHeader) {
                $splitted = preg_split("~(\\r\\n){2}~", $response, 2);
                if (isset($splitted[1])) {
                    $this->responseHeader = $this->parseHeaders($splitted[0]);
                    $response = trim($splitted[1]);
                }
            }
            if (!$response && curl_errno($this->curl) != 0) {
                $this->errorno = curl_errno($this->curl);
                $this->error = curl_error($this->curl);
            }
            $this->referer = curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL);
            $this->http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            //$this->info = curl_getinfo($this->curl);
            $this->effectiveUrl = curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL);
            $this->extractTimeinfo();
            curl_close($this->curl);
            return $response;
        }

        /**
         * @param string $url
         * @param array $params
         * @param string $host
         * @param integer $timeout
         * @return mixed
         * @author mregner
         */
        public function INFO($url, array $params = null, $host = null, $timeout = 0)
        {
            $this->delay();
            $this->init();
            if (isset($params)) {
                $queryString = $this->toQueryString($params);
                $url .= "?" . $queryString;
            }
            curl_setopt($this->curl, CURLOPT_URL, $url);
            if (isset ($this->port)) {
                curl_setopt($this->curl, CURLOPT_PORT, $this->port);
            }
            if (isset($host)) {
                $this->header[] = 'Host: ' . $host;
            }
            if (!empty($this->header)) {
                curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->header);
            }
            if ($timeout != false) {
                curl_setopt($this->curl, CURLOPT_TIMEOUT, $timeout);
            }
            curl_setopt($this->curl, CURLOPT_HEADER, true);
            curl_setopt($this->curl, CURLINFO_HEADER_OUT, true);
            $response = curl_exec($this->curl);
            $result = array();
            if (!$response && curl_errno($this->curl) != 0) {
                $this->errorno = curl_errno($this->curl);
                $this->error = curl_error($this->curl);
            } else if ($response) {
                list($this->header) = preg_split("~(\\r\\n){2}~", $response);
                $headerPattern = "~([a-zA-Z0-9_-]+):\s*(.*)\\r\\n~i";
                if (preg_match_all($headerPattern, $this->header, $matches)) {
                    foreach ($matches[1] as $index => $key) {
                        $result[$key] = $matches[2][$index];
                    }
                }
            }
            $result['NAMELOOKUP_TIME'] = curl_getinfo($this->curl, CURLINFO_NAMELOOKUP_TIME);
            $result['CONNECT_TIME'] = curl_getinfo($this->curl, CURLINFO_CONNECT_TIME);
            $result['PRETRANSFER_TIME'] = curl_getinfo($this->curl, CURLINFO_PRETRANSFER_TIME);
            $result['STARTTRANSFER_TIME'] = curl_getinfo($this->curl, CURLINFO_STARTTRANSFER_TIME);
            $result['REDIRECT_TIME'] = curl_getinfo($this->curl, CURLINFO_REDIRECT_TIME);
            $result['TOTAL_TIME'] = curl_getinfo($this->curl, CURLINFO_TOTAL_TIME);
            $result['SIZE_DOWNLOAD'] = curl_getinfo($this->curl, CURLINFO_SIZE_DOWNLOAD);
            $result['SIZE_UPLOAD'] = curl_getinfo($this->curl, CURLINFO_SIZE_UPLOAD);
            $result['SPEED_DOWNLOAD'] = curl_getinfo($this->curl, CURLINFO_SPEED_DOWNLOAD);
            $result['SPEED_UPLOAD'] = curl_getinfo($this->curl, CURLINFO_SPEED_UPLOAD);
            $result['HEADER_OUT'] = curl_getinfo($this->curl, CURLINFO_HEADER_OUT);
            $this->referer = curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL);
            $this->http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            $this->effectiveUrl = curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL);
            curl_close($this->curl);
            return $result;
        }

        /**
         * @param array $values
         * @return string
         * @author mregner
         */
        public function toQueryString(array $values) {
            $elements = array();
            foreach ($values as $key => $value) {
                if (!is_array($value)) {
                    $key = urlencode($key);
                    $value = urlencode($value);
                    $elements[] = "{$key}={$value}";
                } else {
                    foreach ($value as $index => $indexValue) {
                        $elements[] = urlencode("{$key}[{$index}]") . '=' . urlencode($indexValue);
                    }
                }
            }
            return implode("&", $elements);
        }

        /**
         * @param string $valuestring
         * @return array
         * @author mregner
         */
        public function fromQueryString($valuestring) {
            $queryParams = array();
            $pairs = explode('&', $valuestring);
            for ($i = 0; $i < count($pairs); $i++) {
                list ($name, $encvalue) = explode('=', $pairs[$i]);
                if (!isset ($encvalue)) {
                    $encvalue = '';
                }
                $queryParams[$name] = urldecode($encvalue);
            }
            return $queryParams;
        }

        /**
         * @param string $header_string
         * @return array
         * @author mregner
         */
        protected function parseHeaders($header_string) {
            $headers = array();
            $lines = preg_split("~\r?\n~", $header_string);
            foreach ($lines as $line) {
                if (preg_match("~:~", $line)) {
                    list($key, $value) = preg_split("~:~", $line, 2);
                    $headers[$key] = $value;
                } else {
                    $headers[] = $line;
                }
            }
            return $headers;
        }
    }
}
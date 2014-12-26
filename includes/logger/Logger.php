<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 07.12.14
 * Time: 01:34
 */
require_once('includes/init/BootstrapInterface.php');

class Logger implements BootstrapInterface{
    const DEBUG_LEVEL = 0;
    const INFO_LEVEL = 1;
    const WARNING_LEVEL = 2;
    const ERROR_LEVEL = 3;
    const FATAL_LEVEL = 4;
    /**
     * @var Logger
     */
    private static $instance = null;
    /**
     * Der logger level bestimmt ab wann eine meldung ausgegeben wird
     * und ab wann nicht.
     *
     * @var integer
     */
    private $level = self::WARNING_LEVEL;

    /**
     * Das Ziel in das gelogged werden soll. Muss in einer entsprechenden
     * LoggerOutput Klasse implementiert sein.
     *
     * @var string
     */
    private $target = 'Syslog';
    /**
     * Die Konfiguration kann sich fuer verschieden LoggerOutput
     * Implementierungen unterscheiden.
     *
     * @var array
     */
    private $config = array();
    /**
     * @var LoggerOutputInterface
     */
    private $loggerOutput = null;
    /**
     * Map fuer die Konfiguration die nicht direkt die obigen
     * Zahlen enthalten soll.
     *
     * @var array
     */
    private $configLevelMap = array(
        self::DEBUG_LEVEL => 'debug',
        self::INFO_LEVEL => 'info',
        self::WARNING_LEVEL => 'warning',
        self::ERROR_LEVEL => 'error',
        self::FATAL_LEVEL => 'fatal',
    );
    /**
     * @param array $config
     * @author mregner
     */
    protected function __construct(array $config) {
        if(is_array($config)) {
            $this->config = $config;
            if(isset($config['level'])) {
                $this->level = array_search(strtolower($config['level']),
                    $this->configLevelMap);
            }
            if(isset($config['target'])) {
                $this->target = ucfirst($config['target']);
            }
        }
    }
    /**
     * @return Logger
     * @author mregner      */
    public static function getInstance() {
        if(!isset(self::$instance)) {
        //Wenn kein logger initialisiert wurde dann erzeugen wir
        //einen der auf die Seite schreibt.
            $config = array(
                'target' => 'page',
                'level' => 'warning',
            );
            self::$instance = new Logger($config);
        }
        return self::$instance;
    }
    /**
     * @param array $config
     * @author mregner
     */
    public static function initialize(array $config) {
        if(!isset(self::$instance)) {
            if(is_array($config) && isset($config['target']) && isset($config['level'])) {
                self::$instance = new Logger($config);
            }
        }
    }
    /**
     * @param mixed $anything
     * @author mregner
     */
    public static function debug($anything) {
        self::getInstance()->log($anything, self::DEBUG_LEVEL);
    }
    /**
     * @param mixed $anything
     * @author mregner
     */
    public static function info($anything) {
        self::getInstance()->log($anything, self::INFO_LEVEL);
    }
    /**
     * @param mixed $anything
     * @author mregner
     */
    public static function warning($anything) {
        self::getInstance()->log($anything, self::WARNING_LEVEL);
    }
    /**
     * @param mixed $anything
     * @author mregner
     */
    public static function error($anything) {
        self::getInstance()->log($anything, self::ERROR_LEVEL);
    }
    /**
     * @param mixed $anything
     * @author mregner
     */
    public static function fatal($anything) {
        self::getInstance()->log($anything, self::FATAL_LEVEL);
    }
    /**
     * @return LoggerOutputInterface
     * @author mregner
     */
    protected function getLoggerOutput() {
        if(!isset($this->loggerOutput)) {
            $loggerOutputClass = "LoggerOutput{$this->target}";
            if(!class_exists($loggerOutputClass)) {
                $loggerOutputFile = "{$loggerOutputClass}.php";
                require_once($loggerOutputFile);
            }
            $this->loggerOutput = new $loggerOutputClass($this->config);
        }
        return $this->loggerOutput;
    }
    /**
     * @param mixed $anything
     * @param integer $level
     * @author mregner
     */
    protected function log($anything, $level) {
        if($level >= $this->level) {
            $backtrace = $this->getBacktrace($anything);
            if($anything instanceof Exception) {
                $message = $anything->getMessage();
                if(empty($backtrace)) {
                    $message .= "\nin {$anything->getFile()}";
                    $message .= " ({$anything->getLine()})";
                }
            } else if ($anything === '.') {
                $this->getLoggerOutput()->out(array('message' => $anything), $level);
                return;
            } else {
                $message = (is_string($anything) ? $anything:print_r($anything, true));
            }
            foreach($backtrace as $row) {
                $message .= "\n\t";
                if(isset($row['file'])) {
                    $message .= " {$row['file']}";
                }
                if(isset($row['class'])) {
                    $message .= " {$row['class']}{$row['type']}{$row['function']}({$row['args']})";
                } else if(isset($row['function'])) {
                    $message .= " {$row['function']}({$row['args']})";
                }
                if(isset($row['line'])) {
                    $message .= " ({$row['line']})";
                }
            }
            $time = date('Y-m-d H:i:s');
            $pid = getmypid();
            if(isset($_SERVER['HTTP_HOST'])) {
                $context = $_SERVER['HTTP_HOST'];
            } else {
                $context = gethostname();
            }
            $line = "{$time} - {$context} - [{$pid}] - {$this->configLevelMap[$level]}: {$message}";
            $data = array(
                'message' => $line,
            );
            $this->getLoggerOutput()->out($data, $level);
        }
    }
    /**
     * Wir schneiden alle ErrorHandler und Logger aufrufe ab, da wir die hier nicht
     * benoetigen. Die laenge des traces wird je nach Typ beschnitten.
     *
     * @param array $anything
     * @return array
     */
    protected function getBacktrace(&$anything) {
        if($anything instanceof Exception) {
            $limit = 20;
            $trace = $anything->getTrace();
            array_unshift($trace, array(
                'file' => $anything->getFile(),
                'line' => $anything->getLine(),
            )
            );
        } else {
            $limit = 1;
            $trace = debug_backtrace();
        }
        $trimIndex = 0;
        $backtrace = array();
        foreach($trace as $index => $element) {
            if($index > 0) {
                if(isset($element['class']) && ($element['class'] == 'ErrorHandler' || $element['class'] == 'Logger')) {
                    $trimIndex = $index;
                }
                $isInclude = isset($element['function']) && preg_match("~^include|^require~", $element['function']);
                $backtrace[] = array(
                    'file' => isset($lastElement['file']) ? $lastElement['file']:null,
                    'line' => isset($lastElement['line']) ? $lastElement['line']:null,
                    'class' => isset($element['class']) ? $element['class']:null,
                    'type' => isset($element['type']) ? $element['type']:null,
                    'function' => isset($element['function']) && !$isInclude ? $element['function']:null,
                    'args' => '...',
                );
                if($limit > 1 && $isInclude) {
                    $backtrace[] = array(
                        'file' =>  isset($element['file']) ? $element['file']:null,
                        'line' => isset($element['line']) ? $element['line']:null,
                        'function' => $element['function'],
                        'args' => $lastElement['file'],
                    );
                }
            }
            $lastElement = $element;
        }
        $backtrace = array_slice($backtrace, $trimIndex, $limit);
        return $backtrace;
    }
} 
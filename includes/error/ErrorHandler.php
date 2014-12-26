<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 07.12.14
 * Time: 01:20
 */
require_once('includes/init/BootstrapInterface.php');
require_once("includes/libs/phalcon/Library/Phalcon/Utils/PrettyExceptions.php");

class ErrorHandler implements BootstrapInterface{
    const TYPEHINT_REGEX = '~^Argument \d+ passed to (?:\w+::)?\w+\(\) must be an instance of (\w+), (\w+) given~';
    const UNDEFINED_INDEX_REGEX = '~^Undefined index: ~';
    /**
     * @var Phalcon\Utils\PrettyExceptions
     */
    private static $pe = null;
    /**
     * @var bool
     */
    protected static $TYPEHINTING_ENABLED = false;
    /**
     * @param array $config
     * @author mregner
     */
    public static function initialize(array $config) {
        if (isset($config['pretty_print']) && $config['pretty_print'] === true) {
            self::$pe = new Phalcon\Utils\PrettyExceptions();
        }
        set_error_handler(array('ErrorHandler', 'handleError'));
        set_exception_handler(array('ErrorHandler', 'handleException'));
        register_shutdown_function(array('ErrorHandler', 'handleShutdown'));
        assert_options(ASSERT_WARNING, 0);
        ini_set('display_errors', 0);
    }
    /**
     * @author mregner
     */
    public static function enableTypeHinting() {
        self::$TYPEHINTING_ENABLED = true;
    }
    /**
     * @author mregner
     */
    public static function disableTypeHinting() {
        self::$TYPEHINTING_ENABLED = false;
    }
    /**
     * @param integer $errno
     * @param string  $errstr
     * @param string  $errfile
     * @param integer $errline
     * @param mixed   $errcontext
     * @throws ErrorException
     * @author mregner
     */
    public static function handleError($errno, $errstr, $errfile, $errline, $errcontext) {
        if (error_reporting() === 0) {
            return;
        } else if (self::handleUndefinedIndex($errno, $errstr, $errfile, $errline, $errcontext)) {
            return;
        } else if (self::handleTypeHint($errno, $errstr, $errfile, $errline, $errcontext)) {
            return;
        } else {
            throw new ErrorException($errstr, $errno, E_ERROR, $errfile, $errline);
        }
    }
    /**
     * @param   $errno
     * @param       $errstr
     * @param       $errfile
     * @param       $errline
     * @param mixed $errcontext
     * @return bool
     * @author mregner
     */
    protected static function handleUndefinedIndex($errno, $errstr, $errfile, $errline, $errcontext) {
        if ($errno === E_NOTICE) {
            if (preg_match(self::UNDEFINED_INDEX_REGEX, $errstr, $matches)) {
                return true;
            }
        }
        return false;
    }
    /**
     * @param integer $errno
     * @param string  $errstr
     * @param string  $errfile
     * @param integer $errline
     * @param array   $errcontext
     * @return bool
     * @throws ErrorException
     */
    protected static function handleTypeHint($errno, $errstr, $errfile, $errline, $errcontext) {
        if (self::$TYPEHINTING_ENABLED && $errno == E_RECOVERABLE_ERROR) {
            if (preg_match(self::TYPEHINT_REGEX, $errstr, $matches)) {
                if ($matches[1] === $matches[2]) {
                    return true;
                } else {
                    throw new ErrorException($errstr, $errno, E_ERROR, $errfile, $errline);
                }
            }
        }
        return false;
    }
    /**
     * @param Exception $exception
     * @author mregner
     */
    public static function handleException(Exception $exception) {
        self::out($exception);
    }
    /**
     * @author mregner
     */
    public static function handleShutdown() {
        error_reporting(0);
        $error = error_get_last();
        if (isset($error)) {
            self::out(new ErrorException($error['message'], $error['type'], E_ERROR, $error['file'], $error['line']));
        }
    }
    /**
     * @param Exception $exception
     * @author mregner
     */
    private static function out(Exception $exception) {
        if (class_exists("Logger")) {
            Logger::error($exception);
        }
        if (isset(self::$pe)) {
            if ($exception instanceof ErrorException) {
                print self::$pe->handleError($exception->getCode(), $exception->getMessage(), $exception->getFile(),
                    $exception->getLine());
            } else {
                print self::$pe->handle($exception);
            }
        } else {
        // TODO Show friendly HTTP 500 Page
        }
    }
} 
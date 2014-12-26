<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 07.12.14
 * Time: 00:48
 */
require_once "DataAccessException.php";

/**
 * Class DBConnection
 */
class DBConnection implements BootstrapInterface {
    /**
     * @var DBConnection
     */
    private static $instances = null;
    /**
     * @var null|array
     */
    private static $aliases = null;

    /**
     * Config Keys die in jedem Fall benoetigt werden.
     * @var array
     */
    private static $REQUIRED_CONFIG_KEYS = array(
        'driver',
        'host',
        'port',
        'user',
        'password',
        'database',
        'alias'
    );

    /**
     * @var string
     */
    private $driver = '';
    /**
     * @var string
     */
    private $host = '';
    /**
     * @var string
     */
    private $port = 3306;
    /**
     * @var string
     */
    private $user = '';
    /**
     * @var string
     */
    private $password = '';
    /**
     * @var string
     */
    private $database = '';
    /**
     * @var string
     */
    private $alias = '';

    /**
     * Die PDO Instanz.
     * @var PDO
     */
    private $connection = null;


    /**
     * @param array $config
     * @throws DataAccessException
     */
    public static function initialize(array $config) {
        if(is_array($config)) {
            foreach($config as $dbConfig) {
                $checkConfigArray = array_intersect(self::$REQUIRED_CONFIG_KEYS, array_keys($dbConfig));
                if(count($checkConfigArray) === count(self::$REQUIRED_CONFIG_KEYS)) {
                    isset($dbConfig['attributes']) || $dbConfig['attributes'] = array();
                    isset($dbConfig['schema']) || $dbConfig['schema'] = $dbConfig['database'];
                    self::init(
                        $dbConfig['driver'],
                        $dbConfig['host'],
                        $dbConfig['user'],
                        $dbConfig['password'],
                        $dbConfig['database'],
                        $dbConfig['port'],
                        $dbConfig['schema'],
                        $dbConfig['alias'],
                        $dbConfig['attributes']
                    );
                } else {
                    throw new DataAccessException("Incomplete or invalid database configuration!");
                }
            }
        } else {
            throw new DataAccessException("No database configuration!");
        }
    }

    /**
     * @param $driver
     * @param $host
     * @param $user
     * @param $password
     * @param $database
     * @param int $port
     * @param string $schema
     * @param string $alias
     * @param array $attributes
     */
    private static function init($driver, $host, $user, $password, $database, $port=3306,  $schema='', $alias='', array $attributes=array()) {
        $key = "{$host}:{$port}.{$database}.{$alias}.{$user}";
        if(!isset(self::$instances[$key])) {
            self::$instances[$key] = new DBConnection($driver, $host, $user, $password, $database, $port, $schema, $alias, $attributes);
        }
        $alias = ($alias != '' ? $alias:'main');
        self::$aliases[$alias] = self::$instances[$key];
    }

    /**
     * @param $driver
     * @param $host
     * @param $user
     * @param $password
     * @param $database
     * @param int $port
     * @param string $schema
     * @param string $alias
     * @param array $attributes
     */
    protected function __construct($driver, $host, $user, $password, $database, $port=3306, $schema='', $alias, $attributes=array()) {
        $this->driver = $driver;
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
        $this->alias = $alias;
        $this->schema = $schema;
        $this->attributes = $attributes;
    }

    protected function createConnection() {
        $dsn = "{$this->driver}:host={$this->host};port={$this->port};dbname={$this->database}";
        $connection = new PDO($dsn, $this->user, $this->password, $this->attributes);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        return $connection;     }
    /**
     * @return PDO
     *
     * @author mregner
     */
    protected function getConnection() {
        if(is_null($this->connection)) {
            $this->connection = $this->createConnection();
        }
        return $this->connection;
    }
} 
<?php
/**
 * DB package
 *
 * Classes for work with database
 *
 * @package DB
 * @since Version 1.0
 *
 */
namespace DB;

/**
 * Class BasePDO
 * @package DB
 * @version 1.0
 * @since 1.0
 * @author Volkov Danil <vlkv.d.a@gmail.com>
 */
abstract class BasePDO extends \PDO
{

    /**
     * @var \PDOStatement last statement that was run by this object
     */
    protected $_lastStatement = null;

    const ECB = '`';
    const ECE = '`';

    protected static $defaultOptions = array(
        self::ATTR_ERRMODE => self::ERRMODE_EXCEPTION,
        self::ATTR_DEFAULT_FETCH_MODE => self::FETCH_ASSOC,
        self::ATTR_PERSISTENT => true
    );

    /**
     * Creates new instance
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array $options
     * @return self
     * @throws \Exception
     */
    public static function create($dsn, $username = null, $password = null, $options = null) {
        $db = new static($dsn, $username, $password, array_merge(null === $options ? array() : $options, static::$defaultOptions));
        if (!($db instanceof static)) {
            throw new \Exception("PDO {$dsn} not created!");
        }

        return $db;
    }

    public static function resolveClass($dsn) {
        $classes = [
            'mysql' => '\DB\MySQLPDO',
            'mssql' => '\DB\MSSQLPDO',
        ];
        
        $class = explode(':', $dsn);
        
        return isset($classes[$class[0]]) ? $classes[$class[0]] : '\DB\MySQLPDO';
    }

    public static function construct($params) {
        if (!isset($params['dsn'])) {
            throw new \Exception("No DSN specified!");
        }
        $dsn = $params['dsn'];
        $username = isset($params['username']) ? $params['username'] : '';
        $passwd = isset($params['passwd']) ? $params['passwd'] : '';

        $class = isset($params['class']) ? $params['class'] : static::resolveClass($dsn);
        
        unset($params['dsn']);
        unset($params['username']);
        unset($params['passwd']);
        unset($params['class']);
        
        return $class::create($dsn, $username, $passwd, $params);
    }

    /**
     * Execute some sql
     * @param string $sql
     * @param array $params
     * @return \PDOStatement
     * @throws \Exception
     */
    public function execute($sql, $params = []) {
        $this->_lastStatement = $this->prepare($sql);
        if (!($this->_lastStatement instanceof \PDOStatement)) {
            throw new \Exception("{$this->getErrorMessage()}.\n SQL: {$sql} can not be prepared!");
        }

        if (!$this->_lastStatement->execute($params)) {
            throw new \Exception("{$this->getErrorMessage()}.\n SQL: {$sql} can not be executed!");
        }

        return $this->_lastStatement;
    }

    /**
     * Returns error message
     * @return string
     */
    public function getErrorMessage() {
        if ($this->_lastStatement instanceof \PDOStatement) {
            if ('00000' !== $this->_lastStatement->errorCode()) {
                $error = $this->_lastStatement->errorInfo();
                if (!empty($error[2])) {
                    return $error[2];
                }
            }
        }
        if ('00000' !== $this->errorCode()) {
            $error = $this->errorInfo();
            if (!empty($error[2])) {
                return $error[2];
            }
        }
        return '';
    }

    /**
     * Change isolation level of transaction
     * @override
     * @throws \Exception
     */
    public function beginTransaction() {
        if (parent::beginTransaction()) {
            $this->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;');
        } else {
            throw new \Exception('Transaction does not created!');
        }
    }

    /**
     * Is this table exists
     * @param string $tableName
     * @return boolean
     */
    public abstract function isTableExists($tableName);

    /**
     * Is this table like params
     * @param string $tableName
     * @param array $params
     * @return boolean
     */
    public function isTableEqual($tableName, $params) {
        $sql = "SHOW COLUMNS FROM `{$tableName}`;";
        $meta = $this->execute($sql)->fetchAll(BasePDO::FETCH_ASSOC);
        $columns = [];
        foreach($meta as $column) {
            $columns[$column['Field']] = $column;
        }

        foreach($params['fields'] as $field => $param) {
            if (!isset($columns[$field]) || ($param['type'] == $columns['Type'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Drop table
     * @param string $tableName
     * @param boolean $ifExists
     * @return \PDOStatement
     * @throws \Exception
     */
    public function dropTable($tableName, $ifExists = true) {
        $ifExists = $ifExists ? 'IF EXISTS' : '';
        $sql = "DROP TABLE {$ifExists} `{$tableName}`;";
        return $this->execute($sql);
    }

    /**
     * Creates new table by params
     * @param string $tableName
     * @param array $params
     * @param boolean $ifNotExists
     * @return \PDOStatement
     * @throws \Exception
     */
    public abstract function createTable($tableName, $params, $ifNotExists = true);

    public abstract function getColumns($tableName);

}
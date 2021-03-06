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
namespace wh1tew0lf\DB;

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

    /** Escape char begin */
    const ECB = '`';
    /** Escape char end */
    const ECE = '`';

    /** @var array default options for PDO connection */
    protected static $defaultOptions = [
        self::ATTR_ERRMODE => self::ERRMODE_EXCEPTION,
        self::ATTR_DEFAULT_FETCH_MODE => self::FETCH_ASSOC,
        self::ATTR_PERSISTENT => true
    ];

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
        $db = new static($dsn, $username, $password, array_merge(null === $options ? [] : $options, static::$defaultOptions));
        if (!($db instanceof static)) {
            throw new \Exception("PDO {$dsn} not created!");
        }

        return $db;
    }

    /**
     * Resolve db class by dsn
     * @param string $dsn DSN for db access
     * @return mixed|string
     * @throws \Exception
     */
    public static function resolveClass($dsn) {
        $classes = [
            'mysql' => '\wh1tew0lf\DB\MySQLPDO',
            'mssql' => '\wh1tew0lf\DB\MSSQLPDO',
            'odbc' => '\wh1tew0lf\DB\MSSQLPDO',
        ];
        
        $class = explode(':', $dsn);

        if (!isset($classes[$class[0]])) {
            throw new \Exception('Unsupported dsn');
        }
        return $classes[$class[0]];
    }

    /**
     * Create new instance for db access by params
     * @param array $params should contain:
     * <ul><li>dsn - Access line to db</li>
     * <li>username - username if needed</li>
     * <li>passwd - password if needed</li></ul>
     * @return static
     * @throws \Exception
     */
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
        if ($this->_lastStatement instanceof \PDOStatement) {
            $this->_lastStatement->closeCursor();
        }
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
     * @param array $columns
     * @return boolean
     */
    public function isTableEqual($tableName, $columns) {
        $tableColumns = $this->getColumns($tableName);

        foreach ($tableColumns as $name => $data) {
            if (!isset($columns[$name]) || array_diff($data, $columns[$name])) {
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
    public abstract function dropTable($tableName, $ifExists = true);

    /**
     * Creates new table by params
     * @param string $tableName
     * @param array $params
     * @param boolean $ifNotExists
     * @return \PDOStatement
     * @throws \Exception
     */
    public abstract function createTable($tableName, $params, $ifNotExists = true);

    /**
     * Returns columns in format array(fieldName => array(
     * <ul><li>type - database type</li>
     * <li>null - boolean is null</li>
     * <li>default - string default value if exists</li>
     * <li>primary - boolean is primary key</li>
     * <li>key - boolean is key</li>
     * <li>extra - string some extra data</li></ul>
     * @param $tableName
     * @return array
     * @throws \Exception
     */
    public abstract function getColumns($tableName);

    /**
     * Returns SQLBuilder class for create query for this db
     * @return \wh1tew0lf\SQLBuilder\BaseSQLBuilder
     */
    public abstract function getSQLBuilder();

    /**
     * Drop table
     * @param string $tableName
     * @return \PDOStatement
     * @throws \Exception
     */
    public abstract function truncateTable($tableName);

    /**
     * Get all columns from table
     * @param \wh1tew0lf\SQLBuilder\BaseSQLBuilder $sql
     * @return array
     */
    public function extractColumns(&$sql) {
        $tables = $sql->getTables();
        $extractedColumns = [];
        //['ID' => ['type' => 'int', 'null' => false, 'default' => '', 'primary' => true, 'key' => true, 'extra' => 'auto_increment']];
        foreach ($tables as $alias => $table) {
            $columns = $this->getColumns($table);
            foreach ($columns as $columnName => $columnData) {
                if ($columnData['primary']) {
                    $columnData['primary'] = false;
                    $columnData['extra'] = '';
                }
                $extractedColumns["{$alias}_{$columnName}"] = $columnData;
            }
        }
        return $extractedColumns;
    }

    /**
     * Get all columns from table
     * @param \wh1tew0lf\SQLBuilder\BaseSQLBuilder $sql
     * @return array
     */
    public function getSelectAll(&$sql) {
        $tables = $sql->getTables();
        $select = [];
        foreach ($tables as $alias => $table) {
            $columns = $this->getColumns($table);
            foreach ($columns as $columnName => $columnData) {
                $select["{$alias}_{$columnName}"] = "{$alias}.{$columnName}";
            }
        }
        return $select;
    }

}
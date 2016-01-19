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
class BasePDO extends \PDO
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
     * @param string $dns
     * @param string $username
     * @param string $password
     * @param array $options
     * @return self
     * @throws \Exception
     */
    public static function create($dns, $username = null, $password = null, $options = null) {
        $db = new static($dns, $username, $password, array_merge(null === $options ? array() : $options, static::$defaultOptions));
        if (!($db instanceof static)) {
            throw new \Exception("PDO {$dns} not created!");
        }

        return $db;
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

}
<?php
/**
 * SQLBuilder package
 *
 * Classes for generate SQL queries (select/insert/update/delete) on different platforms (MySQL, MS SQL)
 *
 * @package SQLBuilder
 * @since Version 1.0
 *
 */
namespace SQLBuilder;

/**
 * Class MySQLBuilder
 * @package SQLBuilder
 * @version 1.0
 * @since 1.0
 * @author Volkov Danil <vlkv.d.a@gmail.com>
 */
class MySQLBuilder extends BaseSQLBuilder {
    /**
     * @var string Front Escape Character
     */
    protected static $_fec = '`';

    /**
     * @var string Front Escape Character
     */
    protected static $_bec = '`';

    /**
     * Generate LIMIT statement for mysql
     * @return string
     */
    public function genLimitation() {
        if (isset($this->_query['limit'])) {
            return 'LIMIT ' . (isset($this->_query['offset']) ? ($this->_query['offset'] . ', ') : '') . $this->_query['limit'] . "\n";
        }
        return '';
    }

    /**
     * Dummy method
     * @override
     * @param int $level Count of tabs at left side
     * @return string
     */
    public function getSQL($level = 1) {
        return parent::getSQL($level) . $this->genLimitation();
    }

    /**
     * Inserts to table fields
     * @param string $table
     * @param array $fields
     * @return boolean
     */
    public function insertOnDuplicateUpdate($table, $fields) {
        $table = $this->_wrap($table);
        $sql = [];
        foreach ($fields as $key => $value) {
            $sql[] = static::$_bec . $key . static::$_fec . " = $value";
        }
        $sql = "INSERT INTO {$table} (" . static::$_bec .
            implode(static::$_fec . ', ' . static::$_bec, array_keys($fields)) .
            static::$_fec . ') VALUES (' . implode(', ', $fields) . ')' .
            " ON DUPLICATE KEY UPDATE " . implode(',', $sql);
        return $sql;
    }
}
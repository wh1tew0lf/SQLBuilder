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


class MySQLBuilder extends BaseSQLBuilder
{
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

    public function getSQL($level = 1) {
        return parent::getSQL($level) . $this->genLimitation();
    }
}
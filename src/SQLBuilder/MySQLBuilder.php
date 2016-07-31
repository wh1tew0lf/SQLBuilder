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
     * @override
     * @inheritdoc
     */
    public function getSQL($level = 1) {
        return parent::getSQL($level) . $this->genLimitation();
    }
}
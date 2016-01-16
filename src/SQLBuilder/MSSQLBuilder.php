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


class MSSQLBuilder extends BaseSQLBuilder
{

    /**
     * @var string Front Escape Character
     */
    protected static $_bec = '[';
    /**
     * @var string Front Escape Character
     */
    protected static $_fec = ']';

    /**
     * @override
     * @inheritdoc
     */
    public function genSelect() {
        return (isset($this->_query['limit']) ? (' TOP ' . $this->_query['limit'] . ' ') : '') . parent::genSelect();
    }
}
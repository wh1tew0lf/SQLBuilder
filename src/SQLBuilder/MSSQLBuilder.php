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
namespace wh1tew0lf\SQLBuilder;

/**
 * Class MSSQLBuilder
 * @package SQLBuilder
 * @version 1.0
 * @since 1.0
 * @author Volkov Danil <vlkv.d.a@gmail.com>
 */
class MSSQLBuilder extends BaseSQLBuilder {

    /**
     * @var string Front Escape Character
     */
    protected static $_bec = '[';
    /**
     * @var string Front Escape Character
     */
    protected static $_fec = ']';

    /**
     * Generates string for SELECT
     * @override
     * @return string
     */
    public function genSelect() {
        return (!empty($this->_query['limit']) ? (' TOP ' . $this->_query['limit'] . ' ') : '') . parent::genSelect();
    }

    /**
     * Dummy method
     * @override
     * @param int $level Count of tabs at left side
     * @return string
     */
    public function getSQL($level = 1) {
        if (isset($this->_query['offset'])) {
            $uniqueAlias = uniqid('row_number_');
            $select = !empty($this->_query['select']) ? $this->_query['select'] : '*';
            if (empty($this->_query['order']) && ('*' !== $select)) {
                $select = [];
                foreach ($this->_query['select'] as $alias => $expression) {
                    if (is_array($expression)) {
                        foreach ($expression as $fieldName) {
                            $select[] = "{$alias}.{$fieldName}";
                        }
                    } else {
                        $select[] = $expression;
                    }
                }
                $this->_query['order'] = $select;
            }

            if (!empty($this->_query['order'])) {
                $order = $this->genOrderBy();

                if (empty($this->_query['select'])) {
                    $this->select(['*', $uniqueAlias => new BaseExpression("ROW_NUMBER() OVER(ORDER BY {$order})")]);
                } else {
                    $this->addSelect([$uniqueAlias => new BaseExpression("ROW_NUMBER() OVER(ORDER BY {$order})")]);
                }

                $limit = isset($this->_query['limit']) ? $this->_query['limit'] : null;
                unset($this->_query['limit']);
                $offset = $this->_query['offset'];
                unset($this->_query['offset']);
                $tableUniqueAlias = uniqid('temp_');
                unset($this->_query['order']);
                return static::start()
                    ->from([$tableUniqueAlias => $this])
                    ->where(['>', "{$tableUniqueAlias}.{$uniqueAlias}", new BaseExpression($offset)])
                    ->limit($limit)
                    ->order($uniqueAlias)
                    ->getSQL();
            }
        }
        return parent::getSQL($level);
    }
}
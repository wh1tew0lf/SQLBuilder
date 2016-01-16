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
 * BaseSQLBuilder
 *
 * Base class will be parent for other classes. It contains base methods that will be same for all child classes
 *
 * @class Base Class for creating SQL Queries
 * @package SQLBuilder
 * @version 1.0
 * @since 1.0
 * @author Volkov Danil <vlkv.d.a@gmail.com>
 */
class BaseSQLBuilder
{
    /**
     * @var array $_query There will be stored all data of current query
     * @since 1.0
     */
    protected $_query = [];

    /**
     * @var array List of lists of sql operators, key in main list mean count of operands.
     * @since 1.0
     * For example: "=" and key 2 mean that there are two operands, 3 mean many (>= 1)
     */
    protected static $_operators = [
        1 => [
            'not',
            'exists',
        ],
        2 => [
            'is',
            '=',
            '!=',
            '<>',
            '>',
            '<',
            '>=',
            '<=',
            'like',
            'in'
        ],
        3 => [
            'and',
            'or',
        ]
    ];

    /**
     * @var string Front Escape Character
     * @since 1.0
     */
    protected static $_fec = '}';

    /**
     * @var string Front Escape Character
     * @since 1.0
     */
    protected static $_bec = '{';

    /**
     * Clear object state for new query
     * @since 1.0
     * @return self
     */
    public function startQuery()
    {
        $this->_query = [];
        return $this;
    }

    /**
     * Start new instance of SQLBuilder
     * @since 1.0
     * @return BaseSQLBuilder
     */
    public static function start()
    {
        $instance = new self;
        return $instance->startQuery();
    }

    /**
     * Store select statement
     * @since 1.0
     * @param string|array $fields
     * @param string $delimiter If fields is string delimiter for this string
     * @return self
     */
    public function select($fields, $delimiter = ',')
    {
        if (is_string($fields)) {
            $fields = array_map('trim', explode($delimiter, $fields));
        } elseif(!is_array($fields)) {
            $fields = [$fields];
        }
        $this->_query['select'] = $fields;
        return $this;
    }

    /**
     * Returns raw select data
     * @since 1.0
     * @return mixed
     */
    public function getRawSelect()
    {
        return isset($this->_query['select']) ? $this->_query['select'] : null;
    }

    /**
     * Add select parameters to stored select statement
     * @since 1.0
     * @param string|array $fields
     * @param string $delimiter If fields is string delimiter for this string
     * @throws \Exception
     * @return self
     */
    public function addSelect($fields, $delimiter = ',')
    {
        if (!empty($this->_query['select'])) {
            if (is_string($fields)) {
                $fields = array_map('trim', explode($delimiter, $fields));
            } elseif(!is_array($fields)) {
                $fields = [$fields];
            }
            $count = count($this->_query['select']) + count($fields);
            $this->_query['select'] = array_merge($this->_query['select'], $fields);
            if (count($this->_query['select']) != $count) {
                throw new \Exception('Field names conflict!');
            }
            return $this;
        } else {
            return $this->select($fields, $delimiter);
        }
    }

    /**
     * Parse input for from and joins
     * @since 1.0
     * @param array|string|self $tableName Array can contain
     * [0] == table, [1] == alias
     * [table] == table, [alias] == alias
     * @param string|null $alias
     * @return array
     */
    protected function _getTable($tableName, $alias = null) {
        if (is_null($alias)) {
            if (is_string($tableName) && strstr($tableName, ' ')) {
                $parts = explode(' ', $tableName);
                $tableName = $parts[0];
                $alias = $parts[1];
            } elseif (is_array($tableName)) {
                $alias = isset($tableName['alias']) ? $tableName['alias'] : $alias;
                $alias = isset($tableName[1]) ? $tableName[1] : $alias;
                $tableName = isset($tableName['table']) ? $tableName['table'] : $tableName;
                $tableName = isset($tableName[0]) ? $tableName[0] : $tableName;
            }
        }
        return ['table' => $tableName, 'alias' => $alias ? $alias : null];
    }

    /**
     * Store from statement
     * @see \SQLBuilder\BaseSQLBuilder::_getTable For $tableName see getTable method
     * @since 1.0
     * @param array|string|self
     * @param string|null $alias
     * @return self
     */
    public function from($tableName, $alias = null) {
        $this->_query['from'] = $this->_getTable($tableName, $alias);
        return $this;
    }

    /**
     * Adds join statement
     * @see \SQLBuilder\BaseSQLBuilder::_getTable For $tableName see getTable method
     * @since 1.0
     * @param array|string $tableName
     * @param string|array $on
     * @param string|null $alias
     * @param string $type left|right|inner|cross
     * @return self
     */
    public function join($tableName, $on, $alias = null, $type = 'left') {
        $params = $this->_getTable($tableName, $alias);

        $this->_query['join'][] = [
            'table' => $params['table'],
            'alias' => $params['alias'],
            'on' => $on,
            'type' => $type
        ];

        return $this;
    }

    /**
     * Adds INNER join statement
     * @see \SQLBuilder\BaseSQLBuilder::_getTable   For $tableName see getTable method
     * @see \SQLBuilder\BaseSQLBuilder::join        See join method for more details
     * @since 1.0
     * @param array|string $tableName
     * @param string|array $on
     * @param string|null $alias
     * @return self
     */
    public function innerJoin($tableName, $on, $alias = null) {
        return $this->join($tableName, $on, $alias, 'inner');
    }

    /**
     * Adds LEFT join statement
     * @see \SQLBuilder\BaseSQLBuilder::_getTable   For $tableName see getTable method
     * @see \SQLBuilder\BaseSQLBuilder::join        See join method for more details
     * @param array|string $tableName
     * @param string|array $on
     * @param string|null $alias
     * @return self
     */
    public function leftJoin($tableName, $on, $alias = null) {
        return $this->join($tableName, $on, $alias, 'left');
    }

    /**
     * Stores where condition
     *
     * Inline example:
     * <code>['in', 'B', 'C']</code>
     * <code>['in', 'D', ['E', 'F']]</code>
     * <code>['or', 'G', 'H', 'I']</code>
     * <code>['and', 'J', 'K', 'L']</code>
     * <code>['>', 'P', 'Q']</code>
     * <code>['S' => 'T']</code>
     * <code>['is', 'U', 'null']</code>
     * <code>['exist', 'W']</code>
     * <code>['not', 'X']</code>
     * <code>['like', 'Y', 'Z']</code>
     *
     * Instead of each letter can be another expression. For example:
     * <code>['and', 'A', 'B']</code>
     * can be
     * <code>['and', ['or', 'C', 'D'], ['in', 'E', ['F', 'G']]]</code>
     *
     * For string you need to add "'". Example: 'A' => "'Text'"
     * @param array $params ASDF-tree for SQL where condition
     * @return self
     */
    public function where($params = []) {
        $this->_query['where'] = $params;
        return $this;
    }

    /**
     * Adds via AND new where condition
     * @see \SQLBuilder\BaseSQLBuilder::where        See where method for more details
     * @param array $params ASDF-tree for SQL where condition
     * @return self
     */
    public function andWhere($params = []) {
        if (!empty($this->_query['where'])) {
            $this->_query['where'] = [
                'and',
                $this->_query['where'],
                $params
            ];
        } else {
            $this->_query['where'] = ['and', $params];
        }
        return $this;
    }

    /**
     * Adds via OR new where condition
     * @see \SQLBuilder\BaseSQLBuilder::where        See where method for more details
     * @param array $params ASDF-tree for SQL where condition
     * @return self
     */
    public function orWhere($params = []) {
        if (!empty($this->_query['where'])) {
            $this->_query['where'] = [
                'or',
                $this->_query['where'],
                $params
            ];
        } else {
            $this->_query['where'] = ['or', $params];
        }
        return $this;
    }

    /**
     * Sets limit condition for query
     * @param int $limit
     * @return self
     */
    public function limit($limit) {
        $this->_query['limit'] = $limit;
        return $this;
    }

    /**
     * Sets group param state
     * @param string|array $fields
     * @param string $delimiter
     * @return self
     */
    public function group($fields, $delimiter = ',') {
        $fields = is_array($fields) ? $fields : array_map('trim', explode($delimiter, $fields));
        $this->_query['group'] = $fields;
        return $this;
    }

    /**
     * Sets order param state
     * @param string|array $fields
     * @param string $delimiter
     * @return self
     */
    public function order($fields, $delimiter = ',') {
        $fields = is_array($fields) ? $fields : array_map('trim', explode($delimiter, $fields));
        $this->_query['order'] = $fields;
        return $this;
    }

    /**
     * Escape
     * @param array|string $str
     * @return string
     */
    public function _e($str) {
        $str = (is_array($str) && isset($str[0])) ? $str[0]: $str;
        return strpbrk($str, '(+-/*=><)') ? $str :
            (static::$_bec . implode(static::$_bec . '.' . static::$_fec, explode('.', $str)) . static::$_fec) ;
    }

    /**
     * Wrap
     * @param $str
     * @return string
     */
    public function _w($str) {
        return static::$_bec . $str . static::$_fec;
    }

    /**
     * Generates string for SELECT
     * @return string
     */
    public function genSelect() {
        if (empty($this->_query['select'])) {
            return '*';
        } else {
            $select = [];
            foreach($this->_query['select'] as $alias => $expression) {
                if (is_array($expression)) {
                    $tableAlias = $this->_w($alias);
                    foreach($expression as $fieldAlias => $fieldName) {
                        $fieldName = $this->_w($fieldName);
                        if (!is_numeric($fieldAlias)) {
                            $fieldAlias = ' AS ' . $this->_w($fieldAlias);
                        } else {
                            $fieldAlias = '';
                        }
                        $select[] = "{$tableAlias}.{$fieldName}{$fieldAlias}";
                    }
                } elseif ($expression instanceof static) {
                    $subQuery = $expression->buildQuery();
                    //todo what if subQuery use some table or field of query?
                    /*if (isset($this->_query['from']['alias'])) {
                        $subQuery = str_replace('$T$', $this->_query['from']['alias'], $subQuery);
                    }*/

                    if (!is_numeric($alias)) {
                        $alias = ' AS ' . $this->_w($alias);
                    } else {
                        $alias = '';
                    }

                    $select[] = "({$subQuery}){$alias}";

                } else {
                    $expression = ($expression instanceof BaseExpression) ? $expression : $this->_e($expression);
                    if (!is_numeric($alias)) {
                        $alias = ' AS ' . $this->_w($alias);
                    } else {
                        $alias = '';
                    }
                    $select[] = "{$expression}{$alias}";
                }
            }
            return implode(",\n", $select);
        }
    }

    /**
     * Dummy method
     * @return string
     */
    public function buildQuery() {
        return 'SELECT ' . $this->genSelect();
    }
}
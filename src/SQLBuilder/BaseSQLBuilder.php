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
        0 => [
            'is null',
            'is not null',
        ],
        1 => [
            'not',
            'exists',
            'not exists',
        ],
        2 => [
            '=',
            '!=',
            '<>',
            '>',
            '<',
            '>=',
            '<=',
            'like',
            'in',
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
    protected static $_bec = '{';

    /**
     * @var string Front Escape Character
     * @since 1.0
     */
    protected static $_fec = '}';

    /**
     * Clear object state for new query
     * @since 1.0
     * @return static
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
        $instance = new static;
        return $instance->startQuery();
    }

    /**
     * Store select statement
     * @since 1.0
     * @param string|array $fields
     * @param string $delimiter If fields is string delimiter for this string
     * @return static
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
     * @return static
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
     * @param array|string|static $tableName Array can contain
     * [0] == table, [1] == alias
     * [table] == table, [alias] == alias
     * @param string $defaultAlias
     * @return array
     */
    protected function _getTable($tableName, $defaultAlias) {
        if (is_string($tableName) && strstr($tableName, ' ')) {
            $parts = explode(' ', $tableName);
            $tableName = reset($parts);
            $alias = end($parts);
        } elseif (is_string($tableName) && is_numeric($defaultAlias)) {
            $alias = $tableName;
        } elseif (is_array($tableName) && (2 == count($tableName))) {
            $alias = isset($tableName['alias']) ? $tableName['alias'] : $defaultAlias;
            $alias = isset($tableName[1]) ? $tableName[1] : $alias;
            $tableName = isset($tableName['table']) ? $tableName['table'] : $tableName;
            $tableName = isset($tableName[0]) ? $tableName[0] : $tableName;
        } elseif (is_array($tableName) && (1 == count($tableName))) {
            $alias = key($tableName);
            $tableName = reset($tableName);
        }
        return ['table' => $tableName, 'alias' => !empty($alias) ? $alias : $defaultAlias];
    }

    /**
     * Store from statement
     * @see \SQLBuilder\BaseSQLBuilder::_getTable For $tableName see getTable method
     * @since 1.0
     * @param array|string|static
     * @param string|null $alias
     * @return static
     */
    public function from($tableName, $alias = null) {
        $this->_query['from'] = [$this->_getTable($tableName, null === $alias ? 0 : $alias)];
        return $this;
    }

    /**
     * Add one more from statement to stored statements
     * @see \SQLBuilder\BaseSQLBuilder::_getTable For $tableName see getTable method
     * @since 1.0
     * @param array|string|static
     * @param string|null $alias
     * @return static
     */
    public function addFrom($tableName, $alias = null) {
        $defaultAlias = !empty($this->_query['from']) ? count($this->_query['from']) : 0;
        $this->_query['from'][] = $this->_getTable($tableName, null === $alias ? $defaultAlias : $alias);
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
     * @return static
     */
    public function join($tableName, $on, $alias = null, $type = 'left') {
        $defaultAlias = !empty($this->_query['from']) ? count($this->_query['from']) : 0;
        $defaultAlias += !empty($this->_query['join']) ? count($this->_query['join']) : 0;
        $params = $this->_getTable($tableName, null === $alias ? $defaultAlias : $alias);

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
     * @return static
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
     * @return static
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
     * @return static
     */
    public function where($params = []) {
        $this->_query['where'] = $params;
        return $this;
    }

    /**
     * Adds via AND new where condition
     * @see \SQLBuilder\BaseSQLBuilder::where        See where method for more details
     * @param array $params ASDF-tree for SQL where condition
     * @return static
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
     * @return static
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
     * @return static
     */
    public function limit($limit) {
        $this->_query['limit'] = $limit;
        return $this;
    }

    /**
     * Sets offset condition for query
     * @param int $offset
     * @return static
     */
    public function offset($offset) {
        $this->_query['offset'] = $offset;
        return $this;
    }

    /**
     * Sets group param state
     * @param string|array $fields
     * @param string $delimiter
     * @return static
     */
    public function group($fields, $delimiter = ',') {
        if (is_string($fields)) {
            $fields = array_map('trim', explode($delimiter, $fields));
        } elseif(!is_array($fields)) {
            $fields = [$fields];
        }
        $this->_query['group'] = $fields;
        return $this;
    }

    /**
     * Sets group by param state
     * @param string|array $fields
     * @param string $delimiter
     * @return static
     */
    public function groupby($fields, $delimiter = ',') {
        return $this->group($fields, $delimiter);
    }

    /**
     * Add group by parameters to stored group by statement
     * @since 1.0
     * @param string|array $fields
     * @param string $delimiter If fields is string delimiter for this string
     * @throws \Exception
     * @return static
     */
    public function addGroup($fields, $delimiter = ',')
    {
        if (!empty($this->_query['group'])) {
            if (is_string($fields)) {
                $fields = array_map('trim', explode($delimiter, $fields));
            } elseif(!is_array($fields)) {
                $fields = [$fields];
            }
            $count = count($this->_query['group']) + count($fields);
            $this->_query['group'] = array_merge($this->_query['group'], $fields);
            if (count($this->_query['group']) != $count) {
                throw new \Exception('Field names conflict!');
            }
            return $this;
        } else {
            return $this->group($fields, $delimiter);
        }
    }

    /**
     * Add group by parameters to stored group by statement
     * @since 1.0
     * @param string|array $fields
     * @param string $delimiter If fields is string delimiter for this string
     * @throws \Exception
     * @return static
     */
    public function addGroupBy($fields, $delimiter = ',')
    {
        return $this->addGroup($fields, $delimiter);
    }

    /**
     * Sets order param state
     * @param string|array $fields
     * @param string $delimiter
     * @return static
     */
    public function order($fields, $delimiter = ',') {
        if (is_string($fields)) {
            $fields = array_map('trim', explode($delimiter, $fields));
        } elseif(!is_array($fields)) {
            $fields = [$fields];
        }
        $this->_query['order'] = $fields;
        return $this;
    }

    /**
     * Sets order param state
     * @param string|array $fields
     * @param string $delimiter
     * @return static
     */
    public function orderBy($fields, $delimiter = ',') {
        return $this->order($fields, $delimiter);
    }

    /**
     * Add order parameters to stored order statement
     * @since 1.0
     * @param string|array $fields
     * @param string $delimiter If fields is string delimiter for this string
     * @throws \Exception
     * @return static
     */
    public function addOrder($fields, $delimiter = ',')
    {
        if (!empty($this->_query['order'])) {
            if (is_string($fields)) {
                $fields = array_map('trim', explode($delimiter, $fields));
            } elseif(!is_array($fields)) {
                $fields = [$fields];
            }
            $count = count($this->_query['order']) + count($fields);
            $this->_query['order'] = array_merge($this->_query['order'], $fields);
            if (count($this->_query['order']) != $count) {
                throw new \Exception('Field names conflict!');
            }
            return $this;
        } else {
            return $this->order($fields, $delimiter);
        }
    }

    /**
     * Add order parameters to stored order statement
     * @since 1.0
     * @param string|array $fields
     * @param string $delimiter If fields is string delimiter for this string
     * @throws \Exception
     * @return static
     */
    public function addOrderBy($fields, $delimiter = ',')
    {
        return $this->addOrder($fields, $delimiter);
    }

    /**
     * Sets having param state
     * @param string|array $fields
     * @param string $delimiter
     * @return static
     */
    public function having($fields, $delimiter = ',')
    {
        if (is_string($fields)) {
            $fields = array_map('trim', explode($delimiter, $fields));
        } elseif(!is_array($fields)) {
            $fields = [$fields];
        }
        $this->_query['having'] = $fields;
        return $this;
    }

    /**
     * Add having parameters to stored having statement
     * @since 1.0
     * @param string|array $fields
     * @param string $delimiter If fields is string delimiter for this string
     * @throws \Exception
     * @return static
     */
    public function addHaving($fields, $delimiter = ',')
    {
        if (!empty($this->_query['having'])) {
            if (is_string($fields)) {
                $fields = array_map('trim', explode($delimiter, $fields));
            } elseif(!is_array($fields)) {
                $fields = [$fields];
            }
            $count = count($this->_query['having']) + count($fields);
            $this->_query['having'] = array_merge($this->_query['order'], $fields);
            if (count($this->_query['having']) != $count) {
                throw new \Exception('Field names conflict!');
            }
            return $this;
        } else {
            return $this->having($fields, $delimiter);
        }
    }

    /**
     * Escape
     * @param array|string $str
     * @return string
     */
    public function _e($str)
    {
        $str = (is_array($str) && isset($str[0])) ? $str[0]: $str;
        return strpbrk($str, '(+-/*=><)') ? $str :
            (static::$_bec . implode(static::$_fec . '.' . static::$_bec, explode('.', $str)) . static::$_fec) ;
    }

    /**
     * Wrap
     * @param $str
     * @return string
     */
    public function _w($str)
    {
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
                    $subQuery = $expression->getSQL();
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
     * Generates string for FROM
     * @return string
     */
    public function genFrom() {
        $from = [];
        if (!empty($this->_query['from'])) {
            foreach ($this->_query['from'] as $ind => $fromStmt) {
                $alias = $this->_w($fromStmt['alias']);
                if ($fromStmt['table'] instanceof static) {
                    $from[] = '(' . $fromStmt['table']->getSQL() . ") AS {$alias}";
                } elseif ($fromStmt['table'] instanceof BaseExpression) {
                    $from[] = '(' . $fromStmt['table'] . ") AS {$alias}";
                } else {
                    $table = $this->_w($fromStmt['table']);
                    $from[] = ($table != $alias) ? "{$table} AS {$alias}" : $table;
                }
            }
        }

        return implode(",\n", $from);
    }

    public function genJoin() {
        $joins = [];
        if (isset($this->_query['join'])) {
            foreach($this->_query['join'] as $join) {
                $table = $this->_e($join['table']);
                $alias = $this->_e($join['alias']);
                $alias = ($table != $alias) ? " AS {$alias} " : '';
                $on = $this->genWhere($join['on'], true);
                $joins[] = strtoupper($join['type']) . " JOIN {$table}{$alias} ON {$on}";
            }
        }

        return implode("\n", $joins);
    }

    /**
     * Returns array of operators
     * @return array
     */
    public static function getOperators() {
        $result = array();
        foreach (static::$_operators as $place => $operators) {
            foreach ($operators as $name) {
                $result[strtoupper($name)] = $place;
            }
        }
        return $result;
    }

    /**
     * Generate WHERE clause, recursive
     * @param mixed $where
     * @param bool|false $first
     * @return array|string
     * @throws \Exception
     */
    public function genWhere($where, $first = false) {
        $operators = static::getOperators();
        if ($where instanceof static) {
            $where = '(' . $where->getSQL() . ')';
        } elseif (is_null($where)) {
            $where = 'NULL';
        } elseif (is_bool($where)) {
            $where = $where ? 'TRUE' : 'FALSE';
        } elseif (is_array($where)) {
            if (isset($where[0], $operators[strtoupper($where[0])])) {
                $operator = strtoupper($where[0]);
                unset($where[0]);
            } elseif ($first) {
                $operator = (1 == count($where)) ? '=' : 'AND'; // A => B
            } else { //IN array()
                return '(' . implode(', ', $where) . ')';
            }

            if (0 == $operators[$operator]) {
                return $this->genWhere($where[1]) . " {$operator}";
            } elseif (1 == $operators[$operator]) {
                return "{$operator} "  . $this->genWhere($where[1]);
            } elseif ((2 == $operators[$operator])) {
                if (2 == count($where)) {
                    $operand1 = reset($where);
                    $operand2 = end($where);
                } elseif (1 == count($where)) {
                    $operand1 = key($where);
                    $operand2 = reset($where);
                } else {
                    throw new \Exception('Invalid operands count!');
                }

                if (('IN' == $operator) && empty($operand2)) {
                    return '1=0';
                } elseif (('LIKE' == $operator) && !strstr($operand2, '%')) {
                    $operand2 = "'%{$operand2}%'";
                }

                return $this->genWhere($operand1) . " $operator " . $this->genWhere($operand2);
            } else {
                $parts = array();
                foreach ($where as $k => $v) {
                    if (is_array($v)) {
                        $parts[] = $this->genWhere($v);
                    } elseif (is_string($k) && !is_numeric($k)) {
                        $parts[] = $this->genWhere($k) . " = " . $this->genWhere($v);
                    } else {
                        //todo Is it achievable position
                        die('Yes this position is achievable');
                        $parts[] = $this->genWhere($v);
                    }
                }
                return '(' . implode(" \n\t$operator ", $parts) . ')';
            }
        }
        return ($where instanceof BaseExpression) ? $where : $this->_e($where);
    }

    /**
     * Generate GROUP BY statement
     * @return string
     */
    public function genGroupBy() {
        $group = [];
        foreach($this->_query['group'] as $expression) {
            if ($expression instanceof static) {
                $subQuery = $expression->getSQL();
                //todo what if subQuery use some table or field of query?
                /*if (isset($this->_query['from']['alias'])) {
                    $subQuery = str_replace('$T$', $this->_query['from']['alias'], $subQuery);
                }*/

                $group[] = "({$subQuery})";

            } else {
                $expression = ($expression instanceof BaseExpression) ? $expression : $this->_e($expression);
                $group[] = "{$expression}";
            }
        }
        return implode(",\n", $group);
    }

    /**
     * Generate ORDER BY statement
     * @return string
     */
    public function genOrderBy() {
        $order = [];
        foreach($this->_query['order'] as $expression) {
            if ($expression instanceof static) {
                $subQuery = $expression->getSQL();
                //todo what if subQuery use some table or field of query?
                /*if (isset($this->_query['from']['alias'])) {
                    $subQuery = str_replace('$T$', $this->_query['from']['alias'], $subQuery);
                }*/

                $order[] = "({$subQuery})";

            } elseif(is_array($expression) || (is_string($expression) && strstr($expression, ' '))) {
                $expression = is_string($expression) ? explode(' ', $expression) : $expression;
                $orderType = end($expression);
                $expression = reset($expression);
                $order[] = (($expression instanceof BaseExpression) ? $expression : $this->_e($expression)) . ' ' . $orderType;
            } else {
                $order[] = ($expression instanceof BaseExpression) ? $expression : $this->_e($expression);
            }
        }
        return implode(",\n", $order);
    }

    /**
     * Generate HAVING statement
     * @return string
     * @throws \Exception
     */
    public function genHaving() {
        return $this->genWhere($this->_query['having'], true);
    }

    /**
     * Dummy method
     * @return string
     */
    public function getSQL($level = 1) {
        $leftOffset = str_pad('', $level, "\t");
        return $leftOffset . 'SELECT ' . $this->genSelect() . "\n" .
            $leftOffset . 'FROM ' . $this->genFrom() . "\n" .
            $leftOffset . $this->genJoin() . "\n" .
            (!empty($this->_query['where']) ? ($leftOffset . 'WHERE ' . $this->genWhere($this->_query['where'], true) . "\n") : '') .
            (!empty($this->_query['group']) ? ($leftOffset . 'GROUP BY ' . $this->genGroupBy() . "\n") : '') .
            (!empty($this->_query['having']) ? ($leftOffset . 'HAVING ' . $this->genHaving() . "\n") : '') .
            (!empty($this->_query['order']) ? ($leftOffset . 'ORDER BY ' . $this->genOrderBy() . "\n") : '');
    }
}
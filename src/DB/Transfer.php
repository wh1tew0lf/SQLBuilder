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
use \Exception;

/**
 * Class Transfer
 * @package DB
 * @version 1.0
 * @since 1.0
 * @author Volkov Danil <vlkv.d.a@gmail.com>
 */
class Transfer {

    /** @var BasePDO $fromDB */
    private $fromDB = null;
    /** @var BasePDO $toDB */
    private $toDB = null;

    private $portion = 10;

    /**
     * Creates new instance
     * @param BasePDO|array $fromDB
     * @param BasePDO|array $toDB
     * @throws \Exception
     */
    public function __construct($fromDB, $toDB) {
        if ($fromDB instanceof BasePDO) {
            $this->fromDB = $fromDB;
        } elseif(is_array($fromDB)) {
            $this->fromDB = BasePDO::construct($fromDB);
        } else {
            throw new \Exception('Incorrect fromDB param!');
        }

        if ($toDB instanceof BasePDO) {
            $this->toDB = $toDB;
        } elseif(is_array($toDB)) {
            $this->toDB = BasePDO::construct($toDB);
        } else {
            throw new \Exception('Incorrect toDB param!');
        }
    }

    /**
     * Processes each field if necessary by handler at params array and PDO quote
     * @param array $row
     * @param array $columnsMap [oldFieldName => newFieldName]
     * @param array $params [handler => callable, sourceColumns => array]
     * @return array
     */
    private function processFields($row, $columnsMap, $params) {
        $newRow = array();
        foreach($row as $name => &$field) {
            if ((false !== $columnsMap) && !isset($columnsMap[$name])) {
                continue;
            }
            $newName = ((false !== $columnsMap) && isset($columnsMap[$name])) ? $columnsMap[$name] : $name;
            if (isset($params['handler'])) {
                $type = isset($params['sourceColumns'][$name]['type']) ? $params['sourceColumns'][$name]['type'] : false;
                $field = call_user_func($params['handler'], $name, $type, $field);
            }
            $newRow[$newName] = $this->toDB->quote($field);
        }
        return $newRow;
    }

    /**
     * Prepare columns for create new table
     * @param array $columns
     * @param array $params
     * @return mixed
     */
    public function processColumns($columns, $params) {
        $rules = array('default' => 'default', 'whitelist' => 'whitelist', 'blacklist' => 'blacklist');
        if (isset($params['fields'])) {
            $fieldsRule = isset($params['fields'][0]) && isset($rules[strtolower($params['fields'][0])]) ?
                $rules[strtolower($params['fields'][0])] : reset($rules);
            unset($params['fields'][0]);
        }

        if (isset($params['types'])) {
            $typesRule = isset($params['types'][0]) && isset($rules[strtolower($params['types'][0])]) ?
                $rules[strtolower($params['types'][0])] : reset($rules);
            unset($params['types'][0]);
        }

        foreach ($columns as $name => $column) {
            if (isset($fieldsRule)) {
                if ('whitelist' === $fieldsRule) {
                    if (!isset($params['fields'][$name])) {
                        unset($columns[$name]);
                    } elseif ($params['fields'][$name] != $name) {
                        $columns[$params['fields'][$name]] = $columns[$name];
                        unset($columns[$name]);
                    }
                } elseif('blacklist' === $fieldsRule) {
                    if (in_array($name, $params['fields'])) {
                        unset($columns[$name]);
                    }
                } elseif (isset($params['fields'][$name]) && ($params['fields'][$name] != $name)) {
                    $columns[$params['fields'][$name]] = $columns[$name];
                    unset($columns[$name]);
                }
            }

            if (isset($typesRule)) {
                if ('whitelist' === $typesRule) {
                    $found = false;
                    foreach ($params['types'] as $oldType => $newType) {
                        if (strstr($column['type'], $oldType)) {
                            $found = true;
                            $columns[$name]['type'] = $newType;
                            break;
                        }
                    }
                    if (!$found) {
                        unset($columns[$name]);
                    }
                } elseif('blacklist' === $typesRule) {
                    $found = false;
                    foreach ($params['types'] as $oldType => $newType) {
                        if (strstr($column['type'], $oldType)) {
                            $found = true;
                            break;
                        }
                    }
                    if ($found) {
                        unset($columns[$name]);
                    }
                } else {
                    foreach ($params['types'] as $oldType => $newType) {
                        if (strstr($column['type'], $oldType)) {
                            $columns[$name]['type'] = $newType;
                            break;
                        }
                    }
                }
            }
        }

        return $columns;
    }

    /**
     * Creates array of columns map, that consist of pairs oldFieldName => newFieldName
     * @param array $columns
     * @param array $params
     * @return array|false
     */
    public function createColumnsMap($columns, $params) {
        $columnsMap = array();

        $rules = array('default' => 'default', 'whitelist' => 'whitelist', 'blacklist' => 'blacklist');
        if (isset($params['fields'])) {
            $fieldsRule = isset($params['fields'][0]) && isset($rules[strtolower($params['fields'][0])]) ?
                $rules[strtolower($params['fields'][0])] : reset($rules);
            unset($params['fields'][0]);
        }

        if (isset($params['types'])) {
            $typesRule = isset($params['types'][0]) && isset($rules[strtolower($params['types'][0])]) ?
                $rules[strtolower($params['types'][0])] : reset($rules);
            unset($params['types'][0]);
        }

        if (!isset($fieldsRule) && !isset($typesRule)) {
            return false;
        }
        foreach ($columns as $name => $column) {
            if (isset($fieldsRule)) {
                if ('whitelist' === $fieldsRule) {
                    if (isset($params['fields'][$name])) {
                        $columnsMap[$name] = $params['fields'][$name];
                    }
                } elseif ('blacklist' === $fieldsRule) {
                    if (!in_array($name, $params['fields'])) {
                        $columnsMap[$name] = $name;
                    }
                } else {
                    $columnsMap[$name] = isset($params['fields'][$name]) ? $params['fields'][$name] : $name;
                }
            }

            if (isset($typesRule)) {
                if ('whitelist' === $typesRule) {
                    $found = false;
                    foreach ($params['types'] as $oldType => $newType) {
                        if (strstr($column['type'], $oldType)) {
                            $found = true;
                            break;
                        }
                    }
                    if ($found) {
                        $columnsMap[$name] = $name;
                    }
                } elseif ('blacklist' === $typesRule) {
                    $found = false;
                    foreach ($params['types'] as $oldType => $newType) {
                        if (strstr($column['type'], $oldType)) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $columnsMap[$name] = $name;
                    }
                }
            }
        }

        return $columnsMap;
    }

    /**
     * Copy tables from fromDB to toDB
     * @param string|array $tables
     * @param array $params
     * @throws Exception
     */
    public function copy($tables, $params = []) {
        $tables = !is_array($tables) ? [$tables] : $tables;
        foreach ($tables as $tableName => $tableParams) {
            if (is_int($tableName) && is_string($tableParams)) {
                $fromTable = $tableParams;
                $toTable = $tableParams;
            } elseif (is_string($tableName) && is_string($tableParams)) {
                $fromTable = $tableName;
                $toTable = $tableParams;
            } elseif (is_int($tableName) && is_array($tableParams) && isset($tableParams['sql'])) {
                $select = $tableParams['sql'];
                $fromTable = false;
                $toTable = $tableParams['table'];
            } elseif (is_string($tableName) && is_array($tableParams) && !isset($tableParams['sql'])) {
                $fromTable = $tableName;
                $toTable = $tableParams['table'];
            } else {
                throw new Exception('Incorrect tables');
            }

            if (is_array($tableParams)) {
                $params = array_merge($tableParams, $params);
            }

            if ((false !== $fromTable) && !$this->fromDB->isTableExists($fromTable)) {
                throw new Exception('Table doesn\'t exists at first table');
            }

            $select = isset($select) ? $select : $this->fromDB->getSQLBuilder()->from($fromTable);

            $useType = !(is_array($tableParams) && isset($tableParams['sql']));

            $sourceColumns = (is_array($tableParams) && isset($tableParams['sql'])) ? $params['columns'] : $this->fromDB->getColumns($fromTable);
            $columns = $this->processColumns($sourceColumns, $params);
            $columnsMap = $this->createColumnsMap($sourceColumns, $params);

            if ($this->toDB->isTableExists($toTable)) {
                if (!$this->toDB->isTableEqual($toTable, $columns)) {
                    $this->toDB->dropTable($toTable);
                    $this->toDB->createTable($toTable, $columns);
                }
            } else {
                $this->toDB->createTable($toTable, $columns);
            }

            $continue = true;
            $offset = 0;
            while ($continue) {
                $continue = false;
                if ($rows = $this->fromDB->execute($select->limit($this->portion)->offset($offset)->getSQL())->fetchAll(BasePDO::FETCH_ASSOC)) {
                    foreach ($rows as $row) {
                        $row = $this->processFields($row, $columnsMap, array_merge($params, array('sourceColumns' => $useType ? $sourceColumns : false)));
                        $insertUpdate = $this->toDB->getSQLBuilder()->insertOnDuplicateUpdate($toTable, $row);

                        if (false === $this->toDB->execute($insertUpdate)) {
                            echo "ERR2\n";
                            continue;
                        }
                    }
                    $continue = true;
                }
                $offset += $this->portion;
            }

            unset($select);
        }
    }
}


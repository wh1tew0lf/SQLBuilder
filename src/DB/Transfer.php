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
    /** @var int $portion size of rows for one select */
    private $portion = 100;

    /** @var array $availableActions actions that can be as $existsAction field */
    private static $availableActions = [
        'rewrite' => 'rewrite',
        'rewriteOrClear' => 'rewriteOrClear',
        'skip' => 'skip',
        'stop' => 'stop',
        'append' => 'append',
    ];

    /** @var string $existsAction What should be done if table exists */
    private $existsAction = 'rewrite';

    /**
     * @return string
     */
    public function getExistsAction() {
        return $this->existsAction;
    }

    /**
     * @param string $existsAction
     * @throws \Exception
     */
    public function setExistsAction($existsAction) {
        if (isset(self::$availableActions[strtolower($existsAction)])) {
            $this->existsAction = self::$availableActions[strtolower($existsAction)];
        } else {
            throw new Exception('Undefined action, action can be ' . implode(',', self::$availableActions));
        }
    }

    /**
     * Creates new instance
     * @param BasePDO|array $fromDB
     * @param BasePDO|array $toDB
     * @throws \Exception
     */
    public function __construct($fromDB, $toDB) {
        if ($fromDB instanceof BasePDO) {
            $this->fromDB = $fromDB;
        } elseif (is_array($fromDB)) {
            $this->fromDB = BasePDO::construct($fromDB);
        } else {
            throw new Exception('Incorrect fromDB param!');
        }

        if ($toDB instanceof BasePDO) {
            $this->toDB = $toDB;
        } elseif (is_array($toDB)) {
            $this->toDB = BasePDO::construct($toDB);
        } else {
            throw new Exception('Incorrect toDB param!');
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
        $newRow = [];
        foreach ($row as $name => &$field) {
            if ((false !== $columnsMap) && !isset($columnsMap[$name])) {
                continue;
            }
            $newName = ((false !== $columnsMap) && isset($columnsMap[$name])) ? $columnsMap[$name] : $name;
            $newNames = is_array($newName) ? $newName : [$newName];
            foreach ($newNames as $newName) {
                $processedField = $field;
                if (isset($params['handler'])) {
                    $type = isset($params['sourceColumns'][$name]['type']) ? $params['sourceColumns'][$name]['type'] : false;
                    $processedField = call_user_func($params['handler'], $newName, $type, $processedField);
                }
                $newRow[$newName] = $this->toDB->quote($processedField);
            }
        }
        return $newRow;
    }

    /**
     * Prepare columns for create new table
     * @param array $columns
     * @param array $params
     * @return mixed
     */
    private function processColumns($columns, $params) {
        $rules = ['default', 'whitelist', 'blacklist'];
        $rules = array_combine($rules, $rules);
        if (isset($params['fields']) && is_array($params['fields'])) {
            $fieldsRule = (isset($params['fields'][0]) && isset($rules[strtolower($params['fields'][0])])) ?
                $rules[strtolower($params['fields'][0])] : reset($rules);
            unset($params['fields'][0]);
        } elseif (isset($params['fields']) && is_callable($params['fields'])) {
            $fieldsRule = $params['fields'];
        }

        if (isset($params['types']) && is_array($params['types'])) {
            $typesRule = (isset($params['types'][0]) && isset($rules[strtolower($params['types'][0])])) ?
                $rules[strtolower($params['types'][0])] : reset($rules);
            unset($params['types'][0]);
        }

        if (!isset($fieldsRule) && !isset($typesRule)) {
            return $columns;
        }

        $processedColumns = [];
        $ind = 0;
        foreach ($columns as $name => $column) {
            if (isset($fieldsRule) && is_callable($fieldsRule)) {
                if ($result = call_user_func($fieldsRule, $ind, $name, $column)) {
                    $processedColumns = array_merge($result, $processedColumns);
                }
            } elseif (isset($fieldsRule)) {
                if ('whitelist' === $fieldsRule) {
                    if (isset($params['fields'][$name])) {
                        $processedColumns[$params['fields'][$name]] = $column;
                    }
                } elseif ('blacklist' === $fieldsRule) {
                    if (!in_array($name, $params['fields'])) {
                        $processedColumns[$params['fields'][$name]] = $column;
                    }
                } elseif (isset($params['fields'][$name])) {
                    $processedColumns[$params['fields'][$name]] = $column;
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
                } elseif ('blacklist' === $typesRule) {
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
            ++$ind;
        }

        return array_reverse($processedColumns);
    }

    /**
     * Creates array of columns map, that consist of pairs oldFieldName => newFieldName
     * @param array $columns
     * @param array $params
     * @return array|false
     */
    private function createColumnsMap($columns, $params) {
        $columnsMap = [];
        $rules = ['default', 'whitelist', 'blacklist'];
        $rules = array_combine($rules, $rules);
        if (isset($params['fields']) && is_array($params['fields'])) {
            $fieldsRule = isset($params['fields'][0]) && isset($rules[strtolower($params['fields'][0])]) ?
                $rules[strtolower($params['fields'][0])] : reset($rules);
            unset($params['fields'][0]);
        } elseif (isset($params['fields']) && is_callable($params['fields'])) {
            $fieldsRule = $params['fields'];
        }

        if (isset($params['types']) && is_array($params['types'])) {
            $typesRule = isset($params['types'][0]) && isset($rules[strtolower($params['types'][0])]) ?
                $rules[strtolower($params['types'][0])] : reset($rules);
            unset($params['types'][0]);
        }

        if (!isset($fieldsRule) && !isset($typesRule)) {
            return false;
        }
        $ind = 0;
        foreach ($columns as $name => $column) {
            if (isset($fieldsRule) && is_callable($fieldsRule)) {
                if ($result = call_user_func($fieldsRule, $ind, $name, $column)) {
                    $columnsMap[$name] = array_keys($result);
                }
            } elseif (isset($fieldsRule)) {
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
            ++$ind;
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

            if ((is_array($tableParams) && isset($tableParams['sql'])) && isset($params['columns'])) {
                $sourceColumns = $params['columns'];
            } elseif ((is_array($tableParams) && isset($tableParams['sql'])) && !isset($params['columns'])) {
                $sourceColumns = $this->fromDB->extractColumns($select);
                $select->select($this->fromDB->getSelectAll($select));
            } else {
                $sourceColumns = $this->fromDB->getColumns($fromTable);
            }

            $columns = $this->processColumns($sourceColumns, $params);
            $columnsMap = $this->createColumnsMap($sourceColumns, $params);

            if ($this->toDB->isTableExists($toTable)) {
                if ('rewrite' == $this->existsAction) {
                    $this->toDB->dropTable($toTable);
                    $this->toDB->createTable($toTable, $columns);
                } elseif (('rewriteOrClear' == $this->existsAction) && $this->toDB->isTableEqual($toTable, $columns)) {
                    $this->toDB->truncateTable($toTable);
                } elseif (('rewriteOrClear' == $this->existsAction) && !$this->toDB->isTableEqual($toTable, $columns)) {
                    $this->toDB->dropTable($toTable);
                    $this->toDB->createTable($toTable, $columns);
                } elseif ('skip' == $this->existsAction) {
                    echo "TABLE '{$fromTable}' SKIPPED BECAUSE '{$toTable}' EXISTS!\n";
                    continue;
                } elseif ('stop' == $this->existsAction) {
                    throw new \Exception("TABLE '{$fromTable}' DO STOP BECAUSE '{$toTable}' EXISTS!");
                } elseif (('append' == $this->existsAction) && $this->toDB->isTableEqual($toTable, $columns)) {
                    //Do nothing
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
                        $row = $this->processFields($row, $columnsMap, array_merge($params, ['sourceColumns' => $useType ? $sourceColumns : false]));
                        $insertUpdate = $this->toDB->getSQLBuilder()->insertOnDuplicateUpdate($toTable, $row);

                        if (false === $this->toDB->execute($insertUpdate)) {
                            throw new Exception('Row can not be inserted');
                            //continue;
                        }
                    }
                    $continue = true;
                }
                $offset += $this->portion;
            }
            unset($select); //for next table
        }
    }
}


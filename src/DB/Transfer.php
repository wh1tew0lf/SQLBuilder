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

    private function processFields(&$row) {
        foreach($row as $name => &$field) {
            $field = "'{$field}'";
        }
        return $row;
    }

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

            if ((false !== $fromTable) && !$this->fromDB->isTableExists($fromTable)) {
                throw new Exception('Table doesn\'t exists at first table');
            }

            $select = isset($select) ? $select : $this->fromDB->getSQLBuilder()->from($fromTable);

            if ($this->toDB->isTableExists($toTable)) {
                ///*if (!$this->toDB->isTableEqual($tableName, $this->fromDB->getColumns($tableName))) {
                    $this->toDB->dropTable($toTable);
                    $this->toDB->createTable($toTable, $this->fromDB->getColumns($fromTable));
                //}*/
            } else {
                $this->toDB->createTable($toTable, $this->fromDB->getColumns($fromTable));
            }

            $continue = true;
            $offset = 0;
            while ($continue) {
                $continue = false;
                if ($rows = $this->fromDB->execute($select->limit($this->portion)->offset($offset)->getSQL())->fetchAll(BasePDO::FETCH_ASSOC)) {
                    foreach ($rows as $row) {
                        $row = $this->processFields($row);
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


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

    public function copy($params) {
        if (is_array($params)) {
            foreach ($params as $tableName) {
                $this->copy($tableName);
            }
        } else {
            $tableName = $params;
            if (!$this->fromDB->isTableExists($tableName)) {
                throw new Exception('Table doesn\'t exists at first table');
            }

            if ($this->toDB->isTableExists($tableName)) {
                ///*if (!$this->toDB->isTableEqual($tableName, $this->fromDB->getColumns($tableName))) {
                    $this->toDB->dropTable($tableName);
                    $this->toDB->createTable($tableName, $this->fromDB->getColumns($tableName));
                //}*/
            } else {
                $this->toDB->createTable($tableName, $this->fromDB->getColumns($tableName));
            }

            $continue = true;
            $offset = 0;
            while ($continue) {
                $continue = false;
                if ($rows = $this->fromDB->execute("SELECT * FROM {$tableName} LIMIT {$offset}, {$this->portion}")->fetchAll(BasePDO::FETCH_ASSOC)) {
                    foreach ($rows as $row) {
                        $row = $this->processFields($row);
                        $insertUpdate = \SQLBuilder\MySQLBuilder::start()->insertOnDuplicateUpdate($tableName, $row);

                        if (false === $this->toDB->execute($insertUpdate)) {
                            echo "ERR2\n";
                            continue;
                        }
                    }
                    $continue = true;
                }
                $offset += $this->portion;
            }
        }
    }
}


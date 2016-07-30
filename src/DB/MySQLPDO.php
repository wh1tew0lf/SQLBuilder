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

/**
 * Class MySQLPDO
 * @package DB
 * @version 1.0
 * @since 1.0
 * @author Volkov Danil <vlkv.d.a@gmail.com>
 */
class MySQLPDO extends BasePDO {

    /**
     * Is this table exists
     * @param string $tableName
     * @return boolean
     */
    public function isTableExists($tableName) {
        $sql = "SHOW TABLES LIKE '{$tableName}';";
        return $this->execute($sql)->fetchColumn() == $tableName;
    }

    public function getColumns($tableName) {
        $sql = "SHOW COLUMNS FROM `{$tableName}`;";
        return $this->execute($sql)->fetchAll(BasePDO::FETCH_ASSOC);
    }

    /**
     * Creates new table by params
     * @param string $tableName
     * @param array $columns
     * @param boolean $ifNotExists
     * @return \PDOStatement
     * @throws \Exception
     */
    public function createTable($tableName, $columns, $ifNotExists = true) {
        $ifNotExists = $ifNotExists ? 'IF NOT EXISTS' : '';
        $create = "CREATE TABLE {$ifNotExists} `{$tableName}` (\n";
        $fields = array();
        foreach($columns as $fieldData) {
            $fields[] =  "`{$fieldData['Field']}` {$fieldData['Type']} NOT NULL "; // . ($name == $params['primary'] ? 'AUTO_INCREMENT' : '');
        }
        $create .= implode(",\n", $fields) . "\n";
        /*$create .= "PRIMARY KEY (`{$params['primary']}`)\n";
        if (!empty($params['keys'])) {
            foreach($params['keys'] as $key) {
                $create .= ", KEY `{$key}` (`{$key}`)";
            }
        }*/
        $create .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=0;";

        return $this->execute($create);
    }
    /* *
     * Creates new table by params
     * @param string $tableName
     * @param array $params
     * @param boolean $ifNotExists
     * @return \PDOStatement
     * @throws \Exception
     */
    /*public function createTable($tableName, $params, $ifNotExists = true) {
        $ifNotExists = $ifNotExists ? 'IF NOT EXISTS' : '';
        $create = "CREATE TABLE {$ifNotExists} `{$tableName}` (\n";
        $fields = array();
        foreach($params['fields'] as $name => $fieldData) {
            $fields[] =  "`{$name}` {$fieldData['type']} NOT NULL "; // . ($name == $params['primary'] ? 'AUTO_INCREMENT' : '');
        }
        $create .= implode(",\n", $fields) . ",\n";
        $create .= "PRIMARY KEY (`{$params['primary']}`)\n";
        if (!empty($params['keys'])) {
            foreach($params['keys'] as $key) {
                $create .= ", KEY `{$key}` (`{$key}`)";
            }
        }
        $create .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=0;";

        return $this->execute($create);
    }*/
}
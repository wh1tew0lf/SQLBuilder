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

    /**
     * Returns columns in format array(fieldName => array(
     * <ul><li>type - database type</li>
     * <li>null - boolean is null</li>
     * <li>default - string default value if exists</li>
     * <li>primary - boolean is primary key</li>
     * <li>key - boolean is key</li>
     * <li>extra - string some extra data</li></ul>
     * @param $tableName
     * @return array
     * @throws \Exception
     */
    public function getColumns($tableName) {
        $sql = "SHOW COLUMNS FROM `{$tableName}`;";
        $rows = $this->execute($sql)->fetchAll(BasePDO::FETCH_ASSOC);
        $columns = [];
        foreach ($rows as $row) {
            $size = explode('(', $row['Type']);
            if (isset($size[1])) {
                $size = trim($size[1], '()');
            } else {
                $size = false;
            }

            $type = explode('(', $row['Type']);
            $type = strtoupper($type[0]);
            if (in_array($type, [
                'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT', 'FLOAT', 'DOUBLE', 'REAL'
            ])) {
                $row['Type'] = strtolower($type);
            }

            $columns[$row['Field']] = [
                'type' => $row['Type'],
                'size' => $size,
                'null' => $row['Null'] === 'YES',
                'default' => $row['Default'],
                'primary' => $row['Key'] === 'PRI',
                'key' => !empty($row['Key']),
                'extra' => $row['Extra'],
            ];
        }
        return $columns;
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
        $fields = [];
        $primary = null;
        $keys = [];
        foreach ($columns as $name => $fieldData) {
            $fieldLine = "`{$name}` {$fieldData['type']} ";
            if (!empty($fieldData['default'])) {
                $fieldLine .= 'DEFAULT ' . $this->quote($fieldData['default']);
            } elseif ($fieldData['null']) {
                $fieldLine .= 'DEFAULT NULL';
            } else {
                $fieldLine .= 'NOT NULL';
            }
            $fieldLine .= ' ' . $fieldData['extra'];
            $fields[] = $fieldLine;
            if ($fieldData['primary']) {
                $primary = $name;
            } elseif ($fieldData['key']) {
                $keys[] = $name;
            }
        }
        $create .= implode(",\n", $fields) . "\n";
        if (null !== $primary) {
            $create .= ",PRIMARY KEY (`{$primary}`)\n";
        }
        if (!empty($keys)) {
            foreach ($keys as $key) {
                $create .= ", KEY `{$key}` (`{$key}`)";
            }
        }
        $create .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=0;";
        return $this->execute($create);
    }

    /**
     * Returns SQLBuilder for this PDO
     * @return \SQLBuilder\BaseSQLBuilder
     */
    public function getSQLBuilder() {
        return \SQLBuilder\MySQLBuilder::start();
    }

    /**
     * Drop table
     * @param string $tableName
     * @param boolean $ifExists
     * @return \PDOStatement
     * @throws \Exception
     */
    public function dropTable($tableName, $ifExists = true) {
        $ifExists = $ifExists ? 'IF EXISTS' : '';
        $sql = "DROP TABLE {$ifExists} `{$tableName}`;";
        return $this->execute($sql);
    }

    /**
     * Remove all rows from table, but don't delete table
     * @param string $tableName
     * @return \PDOStatement
     */
    public function truncateTable($tableName) {
        //$sql = "TRUNCATE TABLE `{$tableName}`;";
        $sql = "DELETE FROM `{$tableName}` WHERE 1=1;";
        return $this->execute($sql);
    }
}
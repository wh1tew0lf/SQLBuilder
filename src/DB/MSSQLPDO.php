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
namespace wh1tew0lf\DB;

/**
 * Class MSSQLPDO
 * @package DB
 * @version 1.0
 * @since 1.0
 * @author Volkov Danil <vlkv.d.a@gmail.com>
 */
class MSSQLPDO extends BasePDO {

    /** Escape char begin */
    const ECB = '[';
    /** Escape char end */
    const ECE = ']';

    /** @var string $schema MS SQL schema */
    private static $schema = 'dbo';

    /**
     * Is this table exists
     * @param string $tableName
     * @return boolean
     */
    public function isTableExists($tableName) {
        $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES ' .
            'WHERE TABLE_SCHEMA= N\'' . self::$schema . '\' AND TABLE_NAME = N\'' . $tableName . "'";
        return $this->execute($sql)->fetchColumn() > 0;
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
        $sql = 'SELECT * FROM INFORMATION_SCHEMA.COLUMNS ' .
            'WHERE TABLE_NAME = N\'' . $tableName . "'";
        $columns = $this->execute($sql)->fetchAll(BasePDO::FETCH_ASSOC);

        $sql = 'SELECT  COL_NAME(ic.OBJECT_ID,ic.column_id) AS ColumnName, i.is_primary_key isPrimary
        FROM sys.indexes AS i 
        INNER JOIN sys.index_columns AS ic ON  i.OBJECT_ID = ic.OBJECT_ID
        AND i.index_id = ic.index_id
        WHERE OBJECT_NAME(ic.OBJECT_ID)=N\'' . $tableName . '\'';
        $keys = array_column($this->execute($sql)->fetchAll(), 'isPrimary', 'ColumnName');

        $attributes = [];
        foreach ($columns as $info) {
            if (stristr($info['DATA_TYPE'], 'varchar')) {
                $info['DATA_TYPE'] = "varchar({$info['CHARACTER_MAXIMUM_LENGTH']})";
            }
            if (stristr($info['DATA_TYPE'], 'ntext')) {
                $info['DATA_TYPE'] = "text";
            }
            $attributes[$info['COLUMN_NAME']] = [
                'type' => $info['DATA_TYPE'],
                'default' => $info['COLUMN_DEFAULT'],
                'size' => !empty($info['CHARACTER_MAXIMUM_LENGTH']) ?
                    $info['CHARACTER_MAXIMUM_LENGTH'] :
                    (empty($info['NUMERIC_SCALE']) ? $info['NUMERIC_PRECISION'] :
                        "{$info['NUMERIC_PRECISION']}, {$info['NUMERIC_SCALE']}"),
                'null' => strtoupper($info['IS_NULLABLE']) == 'YES',
                'primary' => isset($keys[$info['COLUMN_NAME']]) && $keys[$info['COLUMN_NAME']],
                'key' => isset($keys[$info['COLUMN_NAME']]),
                'extra' => isset($keys[$info['COLUMN_NAME']]) && $keys[$info['COLUMN_NAME']] && ('int' == strtolower($info['DATA_TYPE'])) ? 'auto_increment' : '',
            ];
        }
        return $attributes;
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
        $ifNotExists = $ifNotExists ? "IF OBJECT_ID ('" . self::$schema . ".{$tableName}', 'U') IS NULL\n" : '';
        $create = $ifNotExists . "CREATE TABLE [" . self::$schema . "].[{$tableName}] (\n";
        $fields = [];
        $keys = [];
        foreach ($columns as $name => $fieldData) {
            $fieldData['type'] = stristr($fieldData['type'], 'varchar') ? 'nvarchar' : $fieldData['type']; //use unicode
            $fieldData['type'] = (in_array(strtolower($fieldData['type']), ['text', 'longtext'])) ? 'ntext' : $fieldData['type']; //use unicode
            $fieldLine = "[{$name}] {$fieldData['type']} ";
            if (!empty($fieldData['default'])) {
                $fieldLine .= 'DEFAULT ' . $this->quote($fieldData['default']);
            } elseif ($fieldData['null']) {
                $fieldLine .= 'DEFAULT NULL';
            } else {
                $fieldLine .= 'NOT NULL';
            }
            if ('auto_increment' == strtolower($fieldData['extra'])) {
                //$fieldLine .= ' IDENTITY(1,1) ';
            }
            if ($fieldData['primary']) {
                $fieldLine .= ' PRIMARY KEY';
            } elseif ($fieldData['key']) {
                $keys[] = $name;
            }
            $fields[] = $fieldLine;
        }
        $create .= implode(",\n", $fields) . "\n";
        if (!empty($keys)) {
            foreach ($keys as $key) {
                $create .= ", INDEX [IDX_{$key}] NONCLUSTERED ([{$key}])";
            }
        }
        $create .= ");";
        return $this->execute($create);
    }

    /**
     * Returns SQLBuilder for this PDO
     * @return \wh1tew0lf\SQLBuilder\MSSQLBuilder
     */
    public function getSQLBuilder() {
        return \wh1tew0lf\SQLBuilder\MSSQLBuilder::start();
    }

    /**
     * Drop table
     * @param string $tableName
     * @param boolean $ifExists
     * @return \PDOStatement
     * @throws \Exception
     */
    public function dropTable($tableName, $ifExists = true) {
        $ifExists = $ifExists ? "IF OBJECT_ID ('" . self::$schema . ".{$tableName}', 'U') IS NOT NULL\n" : '';
        $sql = $ifExists . "DROP TABLE [{$tableName}];";
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
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
 * Class MSSQLPDO
 * @package DB
 * @version 1.0
 * @since 1.0
 * @author Volkov Danil <vlkv.d.a@gmail.com>
 */
class MSSQLPDO extends BasePDO {

    public function isTableExists($tableName) {
        // TODO: Implement isTableExists() method.
    }

    public function getColumns($tableName) {
        $sql = 'SELECT * FROM INFORMATION_SCHEMA.COLUMNS ' .
            'WHERE TABLE_NAME = N\'' . $tableName . "'";

        $columns = $this->execute($sql)->fetchAll(BasePDO::FETCH_ASSOC);

        $attributes = array();
        foreach($columns as $info) {
            $attributes[$info['COLUMN_NAME']] = array(
                'default' => $info['COLUMN_DEFAULT'],
                'type' => $info['DATA_TYPE'],
                'length' => !empty($info['CHARACTER_MAXIMUM_LENGTH']) ?
                    $info['CHARACTER_MAXIMUM_LENGTH'] :
                    (empty($info['NUMERIC_SCALE']) ? $info['NUMERIC_PRECISION'] :
                        "{$info['NUMERIC_PRECISION']}, {$info['NUMERIC_SCALE']}"),
            );
        }
        return $attributes;
    }

    /**
     * Creates new table by params
     * @param string $tableName
     * @param array $params
     * @param boolean $ifNotExists
     * @return \PDOStatement
     * @throws \Exception
     */
    public function createTable($tableName, $params, $ifNotExists = true) {
        // TODO: Implement isTableExists() method.
    }
}
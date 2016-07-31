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
 * BaseExpression
 *
 * Class for sets non-escaped expressions
 *
 * @class Base class for sets non-escaped expressions
 * @package SQLBuilder
 * @version 1.0
 * @since 1.0
 * @author Volkov Danil <vlkv.d.a@gmail.com>
 */
class BaseExpression {
    /**
     * Some value, that should not be escaped
     * @var string
     */
    protected $_value = '';

    /**
     * Construct new instance of this class
     * @param mixed $value
     */
    public function __construct($value) {
        $this->_value = (string)$value;
    }

    /**
     * Magic method for string saving
     * @return string
     */
    public function __toString() {
        return $this->_value;
    }

    /**
     * Static method for construct new instance of this class
     * @param mixed $value
     * @return static
     */
    public static function c($value) {
        return new static($value);
    }

    /*public static function str($str) {
        $str = iconv('utf-8', 'windows-1251//IGNORE', str_replace(array("'", '\\'), array('"',''), $str));
        return new self("'{$str}'");
    }

    public static function like($str) {
        $str = iconv('utf-8', 'windows-1251//IGNORE', str_replace(array("'", '\\'), array('"',''), $str));
        return new self("'%{$str}%'");
    }*/
}
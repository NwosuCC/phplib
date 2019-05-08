<?php

namespace Orcses\PhpLib\Database\Schema\Columns;


class ColumnType
{
  // String types
  const CHAR = 'CHAR';
  const VARCHAR = 'VARCHAR';

  const TINYTEXT = 'TINYTEXT';
  const TEXT = 'TEXT';
  const MEDIUMTEXT = 'MEDIUMTEXT';
  const LONGTEXT = 'LONGTEXT';

  // Numeric types
  const BIGINT = 'BIGINT';
  const INT = 'INT';
  const MEDIUM_INT = 'MEDIUMINT';
  const SMALL_INT = 'SMALLINT';
  const TINY_INT = 'TINYINT';

  const DECIMAL = 'DECIMAL';
  const FLOAT = 'FLOAT';
  const DOUBLE = 'DOUBLE';

  const TIME = 'TIME';
  const DATE = 'DATE';
  const DATETIME = 'DATETIME';
  const TIMESTAMP = 'TIMESTAMP';
  const YEAR = 'YEAR';

  const ENUM = 'ENUM';
  const SET = 'SET';


  // Types Default Length
  const DEFAULT_LENGTH = [
    // String
    self::CHAR => 255,
    self::VARCHAR => 255,

    self::TINYTEXT => null,
    self::TEXT => null,
    self::MEDIUMTEXT => null,
    self::LONGTEXT => null,

    // Numeric : INT
    self::BIGINT => 20,
    self::INT => 11,
    self::MEDIUM_INT => 9,
    self::SMALL_INT => 6,
    self::TINY_INT => 4,

    // Numeric : REAL
    self::DECIMAL => [10,2],
    self::FLOAT => null,
    self::DOUBLE => null,

    // DateTime
    self::TIME => null,
    self::DATE => null,
    self::DATETIME => null,
    self::TIMESTAMP => 0,  // max 6 (MySQL)
    self::YEAR => null,

    // Array-like
    self::ENUM => ['Y','N'],
    self::SET => ['Value A', 'Value B'],
  ];

  const DEFAULT_VALUE_TYPES = [
    'none' => [
      self::TINYTEXT, self::TEXT, self::MEDIUMTEXT, self::LONGTEXT
    ],
    'string' => [
      self::CHAR, self::VARCHAR
    ],
    'int' => [
      self::BIGINT, self::INT, self::MEDIUM_INT, self::SMALL_INT, self::TINY_INT
    ],
    'numeric' => [
      self::DECIMAL, self::FLOAT, self::DOUBLE
    ],
    'array' => [
      self::ENUM, self::SET
    ],
    'time' => [
      self::TIME, self::DATE, self::DATETIME, self::TIMESTAMP, self::YEAR
    ],
  ];

  protected $name;

  protected $default_length;


  protected static function instance()
  {
    return new static();
  }


  /**
   * @param string $name
   * @return ColumnType
   */
  protected function addProps(string $name)
  {
    $this->name = $name;

    $length = self::DEFAULT_LENGTH[ $name ];

    if(is_array($length)){
      $length = implode(',', $length);
    }

    $this->default_length = $length ?: '';

    return $this;
  }


  public function getName()
  {
    return $this->name;
  }


  public function getDefaultLength()
  {
    return $this->default_length;
  }


  /*public function validateLength(int $length)
  {
    $default_length = $this->getDefaultLength();

    if( ! is_numeric($default_length)){
      return $default_length;
    }
    elseif($length <= 0){
      return false;
    }

    if(in_array($this->getName(), [self::CHAR, self::VARCHAR])){

      return $length <= $default_length;
    }

    return true;
  }*/


  /*public function syncLength(int $length = null)
  {
    $default_length = $this->getDefaultLength();

    if( ! is_numeric($length) || $length > $default_length){
      $length = $default_length;
    }

    return $length;
  }*/


  protected function getReal($value)
  {
    return is_numeric($value) ? $value + 0 : false;
  }


  protected function getInt($value)
  {
    if(false !== ($value = $this->getReal($value))){
      $int_value = (int) $value;

      return $int_value == $value ? $int_value : false;
    }

    return false;
  }


  protected function getString($value)
  {
    return is_string($value) ? $value . '' : false;
  }


  protected function getArray($value)
  {
    return is_array($value) ? $value : false;
  }


  protected function getTime($value)
  {
    $time_defaults = [null, DateTimeColumn::CURRENT_TIMESTAMP];

    return in_array($value, $time_defaults, true) ? $value : false;
  }


  protected function validateWithDefaultType($value, string $type)
  {
    if( ! is_bool($value)){

      switch (strval($type)){
        case 'none'   : return null;
        case 'int'    : return $this->getInt($value);
        case 'numeric': return $this->getReal($value);
        case 'string' : return $this->getString($value);
        case 'array'  : return $this->getArray($value);
        case 'time'   : return $this->getTime($value);
      }
    }

    return false;
  }


  public function matchValue($value)
  {
    if(is_null($value)) {
      return $value;
    }

    foreach (self::DEFAULT_VALUE_TYPES as $default_value_type => $names){

      if(in_array($this->getName(), $names)){

        return $this->validateWithDefaultType( $value, $default_value_type);
      }
    }

    return false;
  }


  public static function decimal()
  {
    return static::instance()->addProps( self::DECIMAL);
  }


  public static function float()
  {
    return static::instance()->addProps( self::FLOAT);
  }


  public static function double()
  {
    return static::instance()->addProps( self::DOUBLE);
  }


  public static function bigInt()
  {
    return static::instance()->addProps( self::BIGINT);
  }


  public static function int()
  {
    return static::instance()->addProps( self::INT);
  }


  public static function mediumInt()
  {
    return static::instance()->addProps( self::MEDIUM_INT);
  }


  public static function smallInt()
  {
    return static::instance()->addProps( self::SMALL_INT);
  }


  public static function tinyInt()
  {
    return static::instance()->addProps( self::TINY_INT);
  }


  public static function char()
  {
    return static::instance()->addProps( self::CHAR);
  }


  public static function varChar()
  {
    return static::instance()->addProps( self::VARCHAR);
  }


  public static function tinyText()
  {
    return static::instance()->addProps( self::TINYTEXT);
  }


  public static function text()
  {
    return static::instance()->addProps( self::TEXT);
  }


  public static function mediumText()
  {
    return static::instance()->addProps( self::MEDIUMTEXT);
  }


  public static function longText()
  {
    return static::instance()->addProps( self::LONGTEXT);
  }


  public static function date()
  {
    return static::instance()->addProps( self::DATE);
  }


  public static function time()
  {
    return static::instance()->addProps( self::TIME);
  }


  public static function dateTime()
  {
    return static::instance()->addProps( self::DATETIME);
  }


  public static function timestamp()
  {
    return static::instance()->addProps( self::TIMESTAMP);
  }


  public static function year()
  {
    return static::instance()->addProps( self::YEAR);
  }


}

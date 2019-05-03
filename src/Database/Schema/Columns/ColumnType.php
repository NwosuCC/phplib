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

  const ENUM = 'ENUM';
  const SET = 'SET';


  // Types Default Length
  const DEFAULT_LENGTH = [
    self::CHAR => 255,
    self::VARCHAR => 255,
    //
    self::TINYTEXT => null,
    self::TEXT => null,
    self::MEDIUMTEXT => null,
    self::LONGTEXT => null,
    //
    // Numeric types : INT
    self::BIGINT => 20,
    self::INT => 11,
    self::MEDIUM_INT => 9,
    self::SMALL_INT => 6,
    self::TINY_INT => 4,
    //
    // Numeric types : REAL
    self::DECIMAL => [10,2],
    self::FLOAT => null,
    self::DOUBLE => null,
    //
    // Array-like types
    self::ENUM => ['Y','N'],
    self::SET => ['Value A', 'Value B'],
  ];

  const DEFAULT_VALUE_TYPES = [
    'int' => [
      self::BIGINT, self::INT, self::MEDIUM_INT, self::SMALL_INT, self::TINY_INT
    ],
    'numeric' => [
      self::DECIMAL, self::FLOAT, self::DOUBLE
    ],
    'array' => [
      self::ENUM, self::SET
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


  public function syncLength(int $length = null)
  {
    pr(['usr' => __FUNCTION__, 'length' => $length, 'def' => $this->default_length,
      'is_null' => is_null($length), 'grter' => $length > $this->default_length,
      ]);

    if( ! $length || $length > $this->default_length){
      $length = $this->default_length;
    }

    return $length;
  }


  public function matchesValue($value)
  {
    $value_type = null;

    foreach (self::DEFAULT_VALUE_TYPES as $default_value_type => $names){

      if(in_array($this->getName(), $names)){
        $value_type = $default_value_type;
        break;
      }
    }

    $check_method = 'is_' . $value_type;

    return call_user_func($check_method. $value);;
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


}

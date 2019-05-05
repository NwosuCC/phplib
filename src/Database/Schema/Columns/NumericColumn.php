<?php

namespace Orcses\PhpLib\Database\Schema\Columns;


use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;


class NumericColumn extends Column
{
  const NUMBERS_INT = [
    ColumnType::BIGINT,
    ColumnType::INT,
    ColumnType::MEDIUM_INT,
    ColumnType::SMALL_INT,
    ColumnType::TINY_INT
  ];

  const NUMBERS_REAL = [
    ColumnType::DECIMAL,
    ColumnType::FLOAT,
    ColumnType::DOUBLE
  ];

  const UNSIGNED = 'unsigned';
  const ZEROFILL = 'zerofill';
  const AUTOINCREMENT = 'auto_increment';
  const PRECISION = 'precision';
  const SCALE = 'scale';

  protected $unsigned;

  protected $zerofill;

  protected $auto_increment;

  protected $precision;

  protected $scale;


  protected function getProps()
  {
    return [
      self::UNSIGNED, self::ZEROFILL, self::AUTOINCREMENT, self::PRECISION, self::SCALE
    ];
  }


  protected function onCreate()
  {
    $this->syncDecimalProps();
  }


  /**
   * Sets the Precision and Scale from the Length iff this column is Type DECIMAL
   */
  protected function syncDecimalProps()
  {
    if( ! $this->isDecimal()){
      return;
    }

    $length = $this->getLength() ?: $this->getType()->getDefaultLength();

    $length = Arr::stripEmpty( explode(',', $length));

    if($length && count($length) === 2){

      $this->setPrecision( (int) $length[0] );

      $this->setScale( (int) $length[1] );
    }
  }


  /**
   * @return NumericColumn
   */
  public function setUnsigned()
  {
    $this->unsigned = true;

    return $this;
  }


  public function getUnsigned()
  {
    return $this->unsigned;
  }


  /**
   * @return NumericColumn
   */
  public function setZerofill()
  {
    $this->zerofill = true;

    return $this;
  }


  public function getZerofill()
  {
    return $this->zerofill;
  }


  /**
   * @return null | NumericColumn
   */
  public function setAutoIncrement()
  {
    if( ! $this->isIntNumber()){
      // If called as part of new Column initialization, ignore Error and return
      if( ! $this->isCreated()){ return null; }

      throw new InvalidArgumentException(
        "AutoIncrement can only be set on integer numbers: " . implode(',',self::NUMBERS_INT)
      );
    }

    // If $flag === true, 'auto_increment' replaces the 'default'
    $this->setHasDefault(false);

    $this->auto_increment = true;

    return $this;
  }


  public function getAutoIncrement()
  {
    return $this->auto_increment;
  }


  protected function isIntNumber()
  {
    return in_array( $this->getTypeName(), self::NUMBERS_INT );
  }


  protected function isRealNumber()
  {
    return in_array( $this->getTypeName(), self::NUMBERS_REAL );
  }


  protected function isDecimal()
  {
    return $this->getTypeName() === ColumnType::DECIMAL;
  }


  /**
   * @param int $precision
   * @return NumericColumn
   */
  public function setPrecision(int $precision)
  {
    // First, check that this column is REAL NUMBER.
    // Set the Significant Digits ==> Overrides 'length' property

    $this->precision = $precision;

    return $this;
  }


  public function getPrecision()
  {
    return $this->precision;
  }


  /**
   * @param int $scale
   * @return NumericColumn
   */
  public function setScale(int $scale)
  {
    // First, check that this column is REAL NUMBER.
    // Set the Decimal Places ==> Overrides 'length' property

    $this->scale = $scale;

    return $this;
  }


  public function getScale()
  {
    return $this->scale;
  }


}
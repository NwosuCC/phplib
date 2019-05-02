<?php

namespace Orcses\PhpLib\Database\Schema\Columns;


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

  const SIGNED = 'signed';
  const ZEROFILL = 'zerofill';
  const AUTOINCREMENT = 'auto_increment';
  const PRECISION = 'precision';
  const SCALE = 'scale';

  protected $signed = true;

  protected $zerofill = false;

  protected $auto_increment = false;

  protected $precision;

  protected $scale;


  protected function getProps()
  {
    return [
      self::SIGNED, self::ZEROFILL, self::AUTOINCREMENT, self::PRECISION, self::SCALE
    ];
  }


  /**
   * @param bool $flag
   * @return NumericColumn
   */
  public function setSigned(bool $flag = true)
  {
    $this->signed = $flag;

    return $this;
  }


  public function getSigned()
  {
    return $this->signed;
  }


  /**
   * @param bool $flag
   * @return NumericColumn
   */
  public function setZerofill(bool $flag = true)
  {
    $this->zerofill = $flag;
  }


  public function getZerofill()
  {
    return $this->zerofill;
  }


  /**
   * @param bool $flag
   * @return NumericColumn
   */
  public function setAutoIncrement(bool $flag = true)
  {
    $this->auto_increment = $flag;
  }


  public function getAutoIncrement()
  {
    return $this->auto_increment;
  }


  protected function isIntNumber()
  {
    return in_array( $this->getType(), self::NUMBERS_INT );
  }


  protected function isRealNumber()
  {
    return in_array( $this->getType(), self::NUMBERS_REAL );
  }


  /**
   * @param int $precision
   * @return NumericColumn
   */
  public function setPrecision(int $precision = null)
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
  public function setScale(int $scale = null)
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


  // Called at final properties collation
  protected function augmentProperties()
  {
    $length = $this->getLength();

    if( ! $length || $length < 0){
      $length = $this->getDefaultLengthForType();
    }

    // If this column is REAL NUMBER, set the Precision and Scale
    if($this->isRealNumber()){

      $this->setPrecision();
    }
  }


}
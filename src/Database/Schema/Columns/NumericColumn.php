<?php

namespace Orcses\PhpLib\Database\Schema\Columns;


class NumericColumn extends Column
{
  const SIGNED = 'signed';
  const ZERO_FILL = 'zerofill';
  const AUTO_INCREMENT = 'auto_increment';
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
      'signed', 'zerofill', 'auto_increment', 'precision', 'scale'
    ];
  }


  public function setSigned(bool $flag)
  {
    $this->signed = $flag;

    return $this;
  }


  public function getSigned()
  {
    return $this->signed;
  }


  public function setZeroFill(bool $flag)
  {
    $this->zerofill = $flag;
  }


  public function getZeroFill()
  {
    return $this->zerofill;
  }


  public function setAutoIncrement(bool $flag)
  {
    $this->auto_increment = $flag;
  }


  public function getAutoIncrement()
  {
    return $this->auto_increment;
  }


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
<?php

namespace Orcses\PhpLib\Database\Schema\Columns;


class DateTimeColumn extends Column
{
  const CREATED_AT  = 'created_at';
  const UPDATED_AT  = 'updated_at';
  const DELETED_AT  = 'deleted_at';

  const HAS_TIME_PART = [
    ColumnType::TIME,
    ColumnType::DATETIME,
    ColumnType::TIMESTAMP
  ];

  const IS_DATETIME = [
    ColumnType::DATETIME,
    ColumnType::TIMESTAMP
  ];

  const CURRENT_TIMESTAMP = 'current_timestamp';
  const ON_UPDATE = 'on_update';
  const PRECISION = 'precision';
  const TIMEZONE  = 'timezone';

  protected $current_timestamp;

  protected $on_update;

  protected $precision;

  protected $timezone;


  protected function onCreate()
  {
    $this->syncPrecision();
  }


  protected function getProps()
  {
    return [
      'current_timestamp', 'on_update', 'precision', 'timezone'
    ];
  }


  /**
   * Sets the Precision from the Length iff this column has TIME part
   */
  protected function syncPrecision()
  {
    if( ! $this->hasTimePart()){
      return;
    }

    $length = $this->getLength() ?: $this->getType()->getDefaultLength();

    if('' !== (string) $length){

      $this->setPrecision( (int) $length );
    }
  }


  /**
   * @return DateTimeColumn
   */
  public function setCurrentTimestamp()
  {
    if( ! $this->isDateTime()){
      // ToDo: should throw Exception ???
      return $this;
    }

    $this->current_timestamp = true;

    $this->setHasDefault(true);

    return $this;
  }


  public function getCurrentTimestamp()
  {
    return $this->current_timestamp;
  }


  /**
   * @return DateTimeColumn
   */
  public function setOnUpdate()
  {
    if( ! $this->isDateTime()){
      // ToDo: should throw Exception ???
      return $this;
    }

    $this->on_update = true;

    return $this;
  }


  public function getOnUpdate()
  {
    return $this->on_update;
  }


  /**
   * Number of decimal places appended to the TIME part
   * E.g. 15:23:04.0000 has precision 4 (the last '0000' - 4 digits)
   * @param int $precision
   * @return DateTimeColumn
   */
  public function setPrecision(int $precision = null)
  {
    if( ! $this->hasTimePart()){
      // ToDo: should throw Exception ???
      return $this;
    }

    $this->precision = $precision;

    $this->setLength( (int) $precision );

    return $this;
  }


  public function getPrecision()
  {
    return $this->precision;
  }


  /**
   * @return DateTimeColumn
   */
  public function setTimezone()
  {
    $this->timezone = true;

    return $this;
  }


  public function getTimezone()
  {
    return $this->timezone;
  }


  protected function hasTimePart()
  {
    return in_array($this->getTypeName(), self::HAS_TIME_PART);
  }


  public function isDateTime()
  {
    return in_array($this->getTypeName(), self::IS_DATETIME);
  }


}
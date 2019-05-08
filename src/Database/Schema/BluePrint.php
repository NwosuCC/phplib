<?php

namespace Orcses\PhpLib\Database\Schema;


use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Database\Schema\Columns\Column;
use Orcses\PhpLib\Database\Schema\Columns\ColumnType;
use Orcses\PhpLib\Database\Schema\Columns\StringColumn;
use Orcses\PhpLib\Database\Schema\Columns\NumericColumn;
use Orcses\PhpLib\Database\Schema\Columns\DateTimeColumn;


class BluePrint
{
  protected $table;


  public function __construct(Table $table)
  {
    $this->table = $table;
  }


  public function bigIncrements(string $name, int $length = null)
  {
    return $this->increments($name, $length, ColumnType::bigInt());
  }


  public function mediumIncrements(string $name, int $length = null)
  {
    return $this->increments($name, $length, ColumnType::mediumInt());
  }


  public function smallIncrements(string $name, int $length = null)
  {
    return $this->increments($name, $length, ColumnType::smallInt());
  }


  public function tinyIncrements(string $name, int $length = null)
  {
    return $this->increments($name, $length, ColumnType::tinyInt());
  }


  public function increments(string $name, int $length = null, ColumnType $type = null)
  {
    $properties = [
      NumericColumn::AUTOINCREMENT => true
    ];

    $column_type = $type ?: ColumnType::int();

    return $this->addNumericColumn(
      $name, $column_type, $this->mergeProps( $length, $properties )
    );
  }


  public function decimal(string $name, int $precision = null, int $scale = null)
  {
    $properties = [
      NumericColumn::PRECISION => $precision,
      NumericColumn::SCALE => $scale
    ];

    return $this->addNumericColumn( $name, ColumnType::decimal(), $properties );
  }


  public function string(string $name, int $length = null)
  {
    return $this->addStringColumn(
      $name, ColumnType::varChar(), $this->mergeProps( $length )
    );
  }


  public function text(string $name)
  {
    return $this->addStringColumn( $name, ColumnType::text(), [] );
  }


  public function timestamp(string $name, int $precision = 0)
  {
    $properties = [
      DateTimeColumn::PRECISION => $precision,
    ];

    return $this->addDateTimeColumn( $name, ColumnType::timestamp(), $properties );
  }


  public function timestamps(array $names = null, int $precision = 0, bool $null = false)
  {
    $names = $names ?: [];

    $created_at = ! is_null($names[0] ?? null) ? $names[0] : DateTimeColumn::CREATED_AT;

    $updated_at = ! is_null($names[1] ?? null) ? $names[1] : DateTimeColumn::UPDATED_AT;

    $this->timestamp( $created_at, $precision )->setNull($null)->setCurrentTimestamp();

    $this->timestamp( $updated_at, $precision )->setNull($null)->setCurrentTimestamp()->setOnUpdate();
  }


  public function nullTimestamps(array $names = null, int $precision = 0)
  {
    $this->timestamps( $names, $precision, true );
  }


  /**
   * @param string $name
   * @return DateTimeColumn
   */
  public function softDeletes(string $name = DateTimeColumn::DELETED_AT)
  {
    return $this->timestamp( $name )->setNull();
  }


  /**
   * Resets the primary keys with the supplied columns
   * @param string[] $columns An array of the names of added/existing columns
   */
  public function primary(...$columns)
  {
    $table = $this->getTable()->clearPrimary();

    foreach(Arr::unwrap( $columns ) as $column){
      $table->setPrimary( $column );
    }
  }


  /**
   * @param string[] $columns An array of the names of added/existing columns
   * @param string $name
   */
  public function unique(array $columns, string $name = null)
  {
    $this->getTable()->setUnique( $columns, $name );
  }


  /**
   * @param string[] $columns An array of the names of added/existing columns
   * @param string $name
   */
  public function index(array $columns, string $name = null)
  {
    $this->getTable()->setIndex( $columns, $name );
  }


  /**
   * @param string[] $columns An array of the names of added/existing columns
   * @param string $name
   */
  public function fulltext(array $columns, string $name = null)
  {
    $this->getTable()->setFulltext( $columns, $name );
  }


  /**
   * @param string[] $columns An array of the names of added/existing columns
   * @param string $name
   */
  public function spatial(array $columns, string $name = null)
  {
    $this->getTable()->setSpatial( $columns, $name );
  }


  public function properties(array $properties)
  {
    $this->getTable()->setProperties( $properties );
  }


  protected function mergeProps(int $length = null, array $properties = null)
  {
    if(is_null($properties)){
      $properties = [];
    }

    if( ! is_null($length)){
      $properties[ Column::LENGTH ] = $length;
    }

    return $properties;
  }


  /**
   * @param string $name      E.g: 'email'
   * @param ColumnType $type  E.g: ColumnType::varChar()
   * @param array $properties E.g: [Column::LENGTH => 64]
   * @return NumericColumn
   */
  protected function addNumericColumn(string $name, ColumnType $type, array $properties)
  {
    $column = app()->build(
      NumericColumn::class, compact('name', 'type', 'properties')
    );

    $this->table->addColumn( $column );

    return $column;
  }


  /**
   * @param string $name      E.g: 'email'
   * @param ColumnType $type
   * @param array $properties E.g: [Column::LENGTH => 64]
   * @return StringColumn
   */
  protected function addStringColumn(string $name, ColumnType $type, array $properties)
  {
    $column = app()->build(
      StringColumn::class, compact('name', 'type', 'properties')
    );

    $this->table->addColumn( $column );

    return $column;
  }


  /**
   * @param string $name      E.g: 'email'
   * @param ColumnType $type
   * @param array $properties E.g: [Column::LENGTH => 64]
   * @return DateTimeColumn
   */
  protected function addDateTimeColumn(string $name, ColumnType $type, array $properties)
  {
    $column = app()->build(
      DateTimeColumn::class, compact('name', 'type', 'properties')
    );

    $this->getTable()->addColumn( $column );

    return $column;
  }


  public function getTable()
  {
    return $this->table;
  }


}
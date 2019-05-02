<?php

namespace Orcses\PhpLib\Database\Schema;


use Orcses\PhpLib\Database\Schema\Columns\Column;
use Orcses\PhpLib\Database\Schema\Columns\ColumnType;
use Orcses\PhpLib\Database\Schema\Columns\StringColumn;
use Orcses\PhpLib\Database\Schema\Columns\NumericColumn;
use Orcses\PhpLib\Exceptions\Database\Schema\DuplicateColumnException;


class BluePrint
{
  protected $table;

  /** @var Column[] $columns */
  protected $columns = [];


  public function __construct(Table $table)
  {
    $this->table = $table;
  }


  public function increments(string $name, int $length)
  {
    $properties = [
      NumericColumn::AUTOINCREMENT => true
    ];

    return $this->addNumericColumn(
      $name, ColumnType::bigInt(), $this->resolveLengthProp( $length, $properties )
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
      $name, ColumnType::varChar(), $this->resolveLengthProp( $length )
    );
  }


  public function text(string $name)
  {
    return $this->addStringColumn( $name, ColumnType::text(), [] );
  }


  protected function resolveLengthProp(int $length = null, array $properties = null)
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

    $this->addColumn( $column );

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

    $this->addColumn( $column );

    return $column;
  }


  protected function addColumn(Column &$column)
  {
    if(array_key_exists($column_name = $column->getName(), $this->columns)){

      throw new DuplicateColumnException( $column_name, $this->table->getName() );
    }

    $this->columns[ $column_name ] = $column;
  }


}
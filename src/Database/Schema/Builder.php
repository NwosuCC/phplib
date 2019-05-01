<?php

namespace Orcses\PhpLib\Database\Schema;


use Orcses\PhpLib\Database\Schema\Columns\Column;
use Orcses\PhpLib\Database\Schema\Columns\StringColumn;
use Orcses\PhpLib\Database\Schema\Columns\NumericColumn;
use Orcses\PhpLib\Exceptions\Database\Schema\DuplicateColumnException;


class Builder
{
  protected $table;

  protected $callback;

  /** @var Column[] $columns */
  protected $columns = [];


  protected function __construct(Table $table, callable $callback)
  {
    $this->table = $table;

    $this->callback = $callback;
  }


  public static function create(string $table, callable $callback)
  {
    return new static( new Table($table), $callback );
  }


  public static function table(string $table, callable $callback)
  {
    $table = (new Table($table))->setExists( true );

    return new static( $table, $callback );
  }


  public function increments(string $name, int $length = null)
  {
    return $this->addNumericColumn( $name, NumericColumn::AUTO_INCREMENT, $length );
  }


  public function string(string $name, int $length = null)
  {
    return $this->addStringColumn( $name, StringColumn::VARCHAR, $length );
  }


  public function text(string $name)
  {
    return $this->addStringColumn( $name, StringColumn::TEXT, null );
  }


  protected function addNumericColumn(string $name, string $type, int $length = null)
  {
    $properties = $length ? [Column::LENGTH => $length] : [];

    $this->addColumn(
    // ToDo: use app()->build()
      $column = new NumericColumn( $name, $type, $properties )
    );

    return $column;
  }


  protected function addStringColumn(string $name, string $type, int $length = null)
  {
    $properties = $length ? [Column::LENGTH => $length] : [];

    $this->addColumn(
      // ToDo: use app()->build()
      $column = new StringColumn( $name, $type, $properties )
    );

    return $column;
  }


  protected function addColumn(Column &$column)
  {
    if(array_key_exists($column_name = $column->getName(), $this->columns)){

      throw new DuplicateColumnException( $column_name, $this->table->getName() );
    }

    $this->columns[ $column_name ] = $column;

    return $this;
  }


}
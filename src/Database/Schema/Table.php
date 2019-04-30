<?php

namespace Orcses\PhpLib\Database\Schema;


use Orcses\PhpLib\Database\Schema\Columns\Column;
use Orcses\PhpLib\Database\Schema\Columns\NumericColumn;


class Table
{
  protected $name;

  protected $columns = [];

  /** @var Column[] */
  protected $primary = [];

  protected $collation = 'utf8mb4_unicode_ci';  // put in config


  public function __construct(string $name, array $attributes = null)
  {
    $this->name = $name;

    $this->addAttributes($attributes);
  }


  public function autoIncrements(string $name)
  {
    $column = new NumericColumn( $name, '' );

    $this->addColumn( $column );

    return $column;
  }


  public function string(string $name)
  {
    return $this->addColumn( $name, 'string' );
  }


  protected function addColumn(Column &$column)
  {
    $this->columns[] = $column;

    return $this;
  }


  protected function addAttributes(array $attributes)
  {

  }


}
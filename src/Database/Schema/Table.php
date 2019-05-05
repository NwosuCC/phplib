<?php

namespace Orcses\PhpLib\Database\Schema;


use Orcses\PhpLib\Utility\Str;
use Orcses\PhpLib\Database\Schema\Columns\Column;
use Orcses\PhpLib\Database\Schema\Columns\NumericColumn;
use Orcses\PhpLib\Exceptions\Database\Schema\ColumnNotFoundException;
use Orcses\PhpLib\Exceptions\Database\Schema\DuplicateColumnException;


class Table
{
  const COMMENT       = 'comment';
  const AUTOINCREMENT = 'auto_increment';
  const AV_ROW_LENGTH = 'av_row_length';
  const MAX_ROW_COUNT = 'max_row_count';
  const ROWS_CHECKSUM = 'auto_increment';  // true | false
  const ROW_FORMAT    = 'auto_increment';  // Compact | Compressed | Default | Dynamic | Fixed | Redundant
  const COLLATION     = 'collation';
  const ENGINE        = 'engine';  // InnoDB | MyISAM

  protected $name;

  protected $exists;

  /** @var Column[] $columns */
  protected $columns = [];

  protected $collation = 'utf8mb4_unicode_ci';  // put in config

  /** @var NumericColumn $auto_increment */
  protected $auto_increment;

  /** @var Column[] $primary */
  protected $primary = [];

  /** @var Index[] $unique */
  protected $unique = [];


  public function __construct(string $name)
  {
    $this->name = $name;
  }


  public function getName()
  {
    return $this->name;
  }


  public function setExists(bool $flag)
  {
    $this->exists = $flag;

    return $this;
  }


  public function getExists()
  {
    return $this->exists;
  }


  public function addColumn(Column &$column)
  {
    if(array_key_exists($column_name = $column->getName(), $this->columns)){

      throw new DuplicateColumnException( $column_name, $this->getName() );
    }

    $this->columns[ $column_name ] = $column;

    return $this;
  }


  public function getColumns()
  {
    return $this->columns;
  }


  public function getColumn(string $name)
  {
    if( ! array_key_exists($name, $this->columns)){

      throw new ColumnNotFoundException( $name, $this->getName() );
    }

    return $this->columns[ $name ] ?? null;
  }


  public function setAutoIncrement(NumericColumn $column)
  {
    // ToDo: If auto_increment is already set, show some Error indication

    $this->auto_increment = $column;

    return $this;
  }


  public function setPrimary(string $column)
  {
    $this->primary[] = $this->getColumn($column);

    return $this;
  }


  public function getPrimary()
  {
    return $this->primary;
  }


  public function resolvePrimary()
  {
    if( ! $this->primary instanceof Index){

      $this->primary = Index::create( Index::PRIMARY, ...$this->primary );
    }

    return $this->primary->resolve();
  }


  public function clearPrimary()
  {
    $this->primary = [];

    return $this;
  }


  public function getDefaultIndexName()
  {
    return "{$this->name}";
  }


  /**
   * @param string[] $columns An array of the names of added/existing columns
   * @param string $name
   * @return static
   */
  public function setUnique(array $columns, string $name = null)
  {
    if(is_null($name)){
      $parts = [$this->name, implode('_', $columns), 'unique'];;
    }

    foreach ($columns as $i => $column){
      $columns[ $i ] = $this->getColumn($column);
    }

    $unique_index = Index::create( Index::UNIQUE, ...$columns )->setName($name);;

    $this->unique[ $unique_index->getName() ] = $unique_index;

    return $this;
  }


  public function getUnique()
  {
    return $this->unique;
  }


  public function resolveUnique()
  {
    foreach ($this->unique as $name => $unique_index){
      /** @var Index[] $unique_index */
      $this->unique[ $name ] = $unique_index->resolve();
    }

    return $this->unique;
  }


  public function clearUnique()
  {
    $this->unique = [];

    return $this;
  }


}


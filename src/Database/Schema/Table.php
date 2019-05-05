<?php

namespace Orcses\PhpLib\Database\Schema;


use Orcses\PhpLib\Database\Schema\Columns\Column;
use Orcses\PhpLib\Database\Schema\Columns\NumericColumn;
use Orcses\PhpLib\Exceptions\Database\Schema\ColumnNotFoundException;
use Orcses\PhpLib\Exceptions\Database\Schema\DuplicateColumnException;
use Orcses\PhpLib\Utility\Str;


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

  /** @var Column[] $unique */
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
    $this->primary[] = $this->getColumn($column)->getName();

    return $this;
  }


  public function getPrimary()
  {
    return $this->primary;
  }


  public function resolvePrimary()
  {
    return implode(',', Str::addBackQuotes( $this->primary ));
  }


  public function clearPrimary()
  {
    $this->primary = [];

    return $this;
  }


  public function setUnique(string $name, Column $columns)
  {
    $this->unique[ $name ] = $columns;

    return $this;
  }


  public function getUnique()
  {
    return $this->unique;
  }


  public function clearUnique()
  {
    $this->unique = [];

    return $this;
  }


}


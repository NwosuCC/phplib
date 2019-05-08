<?php

namespace Orcses\PhpLib\Database\Schema;


use Orcses\PhpLib\Utility\Str;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Database\Schema\Columns\Column;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;


class Index
{
  const PRIMARY  = 'primary';
  const UNIQUE   = 'unique';
  const FULLTEXT = 'fulltext';
  const SPATIAL  = 'spatial';
  const KEY      = 'index';

  protected $name;

  protected $columns = [];

  protected $type;


  /**
   * @param string    $type
   * @param Column[]  $columns
   */
  public function __construct(string $type, Column ...$columns)
  {
    $this->type = $type;

    $this->columns = Arr::unwrap($columns);
  }


  public static function create(string $type, Column ...$columns)
  {
    return new static( $type, ...$columns );
  }


  /**
   * @param string  $name
   * @param string  $table_name
   * @return  static
   */
  public function setName(string $name = null, string $table_name = null)
  {
    $this->name = $name ?: $this->getDefaultName($table_name);

    return $this;
  }


  public function getName()
  {
    return $this->name;
  }


  public function resolve()
  {
    return call_user_func([$this, $this->getMethodName()]);
  }


  protected function getMethodName()
  {
    return Str::camelCase('resolve' . $this->type);
  }


  protected function resolvePrimary()
  {
    $this->validateType( self::PRIMARY );

    $quoted_column_names = Str::addBackQuotes( $this->getColumnNames() );

    return ($clause = trim( implode(',', $quoted_column_names)))
      ? "PRIMARY KEY ({$clause})"
      : null;
  }


  protected function resolveUnique()
  {
    return $this->resolveAny( self::UNIQUE );
  }


  protected function resolveFulltext()
  {
    return $this->resolveAny( self::FULLTEXT );
  }


  protected function resolveSpatial()
  {
    return $this->resolveAny( self::SPATIAL );
  }


  protected function resolveIndex()
  {
    return $this->resolveAny( self::KEY );
  }


  protected function resolveAny(string $type)
  {
    $this->validateType( $type );

    $quoted_index_name = Str::addBackQuotes( $this->getName() );

    $quoted_column_names = Str::addBackQuotes( $this->getColumnNames() );

    $index_type = ($this->type !== self::KEY ? strtoupper($this->type) : '') .' INDEX';

    return ($clause = trim( implode(',', $quoted_column_names)))
      ? "{$index_type} {$quoted_index_name} ({$clause})"
      : null;
  }


  protected function getDefaultName(string $table_name)
  {
    $table_name = $table_name ? $table_name . '_' : '';

    $index_name = '_' . $this->type;

    return $table_name . implode('_', $this->getColumnNames()) . $index_name;
  }


  protected function getColumnNames()
  {
    return Arr::each($this->columns, function (Column $column){
      return $column->getName();
    });
  }


  protected function validateType(string $expected)
  {
    if( ! $this->type === $expected){
      throw new InvalidArgumentException(
        "{$this->getMethodName()} expects Index type {$expected}, got {$this->type}"
      );
    }
  }


}
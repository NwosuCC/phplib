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
  const KEY      = 'key';

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
   * @return  static
   */
  public function setName(string $name = null)
  {
    $this->name = $name ?: $this->getDefaultName();

    return $this;
  }


  public function getName()
  {
    return $this->name;
  }


  public function getDefaultName()
  {
    return "{$this->name}";
  }


  public function resolve()
  {
    $index_method = Str::camelCase('resolve' . $this->type);

    return call_user_func([$this, $index_method]);
  }


  protected function getColumnNames()
  {
    return Arr::each($this->columns, function (Column $column){
      return $column->getName();
    });
  }


  protected function validateType(string $supplied, string $expected, string $method){
    if( ! $supplied === $expected){
      throw new InvalidArgumentException(
        "{$method} expects Index type {$expected}, got {$supplied}"
      );
    }
  }


  protected function resolvePrimary()
  {
    $this->validateType( $this->type, self::PRIMARY, __METHOD__ );

    $quoted_column_names = Str::addBackQuotes( $this->getColumnNames() );

    return ($clause = trim( implode(',', $quoted_column_names)))
      ? "PRIMARY KEY ({$clause})"
      : null;
  }


  protected function resolveUnique()
  {
    $this->validateType( $this->type, self::UNIQUE, __METHOD__ );

    $quoted_index_name = Str::addBackQuotes( $this->getName() );

    $quoted_column_names = Str::addBackQuotes( $this->getColumnNames() );

    return ($clause = trim( implode(',', $quoted_column_names)))
      ? "UNIQUE INDEX {$quoted_index_name} ({$clause})"
      : null;
  }


}
<?php

namespace Orcses\PhpLib\Database\Schema;


use Orcses\PhpLib\Database\Schema\Columns\Column;
use Orcses\PhpLib\Database\Schema\Columns\NumericColumn;
use Orcses\PhpLib\Exceptions\Database\Schema\ColumnNotFoundException;
use Orcses\PhpLib\Exceptions\Database\Schema\DuplicateColumnException;
use Orcses\PhpLib\Exceptions\Database\Schema\UnsupportedTablePropertyException;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Utility\Str;


class Table
{
  // These properties must either be not set to value or be skipped altogether
  const ENGINE         = 'engine';  // InnoDB | MyISAM
  const COLLATION      = 'collate';
  const COMMENT        = 'comment';
  const AUTOINCREMENT  = 'auto_increment';
  const AVG_ROW_LENGTH = 'avg_row_length';
  const MAX_ROW_COUNT  = 'max_rows';
  const ROWS_CHECKSUM  = 'checksum';  // 1
  const ROW_FORMAT     = 'row_format';  // Compact | Compressed | Default | Dynamic | Fixed | Redundant

  const STRING_VALUES = [
    self::COLLATION, self::COMMENT
  ];

  const NUMBER_VALUES = [
    self::AUTOINCREMENT, self::AVG_ROW_LENGTH, self::MAX_ROW_COUNT, self::ROWS_CHECKSUM
  ];

  private $constants;

  protected $name;

  protected $exists;

  protected $properties = [];

  protected $default_properties = [
    self::ENGINE => 'InnoDB',
    self::COLLATION => 'utf8mb4_unicode_ci'
  ];

  /** @var NumericColumn $auto_increment */
  protected $auto_increment;

  /** @var Column[] $primary */
  protected $primary = [];

  /** @var Column[] $columns */
  protected $columns = [];

  /** @var Index[] $indexes */
  protected $indexes = [];


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


  public function setProperties(array $properties)
  {
    $properties = Arr::stripEmpty($properties) + $this->default_properties;

    foreach($properties as $name => $value){

      $this->validateProp( $name );

      switch (true){
        case $this->isStringValue($name) : $value = Str::addSingleQuotes($value); break;
        case $this->isNumberValue($name) : $value = (int) $value; break;
      }

      $this->properties[ $name ] = [strtoupper($name), $value];
    }
  }


  public function resolveProperties()
  {
    if( ! $this->properties){
      $this->setProperties([]);
    }

    $properties = Arr::each($this->properties, function($value){
      return "{$value[0]}={$value[1]}";
    });

    return implode(', ', $properties);
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


  public function setUnique(array $columns, string $name = null)
  {
    return $this->addIndex( Index::UNIQUE, $columns, $name);
  }


  public function setIndex(array $columns, string $name = null)
  {
    return $this->addIndex( Index::KEY, $columns, $name);
  }


  public function setFulltext(array $columns, string $name = null)
  {
    return $this->addIndex( Index::FULLTEXT, $columns, $name);
  }


  public function setSpatial(array $columns, string $name = null)
  {
    return $this->addIndex( Index::SPATIAL, $columns, $name);
  }


  /**
   * @param string $type
   * @param string[] $columns An array of the names of added/existing columns
   * @param string $name
   * @return static
   */
  protected function addIndex(string $type, array $columns, string $name = null)
  {
    foreach ($columns as $i => $column){
      $columns[ $i ] = $this->getColumn($column);
    }

    $index = Index::create( $type, ...$columns )->setName($name, $this->getName());;

    $this->indexes[ $index->getName() ] = $index;

    return $this;
  }


  public function resolveIndexes()
  {
    foreach ($this->indexes as $name => $index){
      /** @var Index[] $index */
      $this->indexes[ $name ] = $index->resolve();
    }

    return implode(',', array_values( $this->indexes ));
  }


  protected function isStringValue(string $name)
  {
    return in_array( $name, self::STRING_VALUES );
  }


  protected function isNumberValue(string $name)
  {
    return in_array( $name, self::NUMBER_VALUES );
  }


  protected function getClassConstants(){
    if( ! $this->constants){
      $this->constants = constants(self::class);
    }

    return $this->constants;
  }


  protected function validateProp(string $name)
  {
    if( ! in_array($name, $this->getClassConstants())) {
      throw new UnsupportedTablePropertyException($name);
    }
  }


}


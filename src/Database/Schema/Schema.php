<?php

namespace Orcses\PhpLib\Database\Schema;


use Closure;
use Orcses\PhpLib\Database\Schema\Columns\DateTimeColumn;
use Orcses\PhpLib\Utility\Str;
use Orcses\PhpLib\Database\Schema\Columns\Column;
use Orcses\PhpLib\Database\Schema\Columns\NumericColumn;


class Schema
{
  protected $table;

  protected $callback;

  protected $blue_print;


  public function __construct(Table $table, Closure $callback)
  {
    $this->table = $table;

    $this->callback = $callback;
  }


  public static function create(string $table, callable $callback)
  {
    $table = static::getTable( $table );

    static::Schema( $table, $callback );
  }


  public static function table(string $table, callable $callback)
  {
    $table = static::getTable( $table )->setExists( true );

    static::Schema( $table, $callback );
  }


  /**
   * @param string $table
   * @return Table
   */
  protected static function getTable(string $table)
  {
    return app()->build(Table::class, ['name' => $table]);
  }


  protected static function Schema(Table $table, callable $callback)
  {
    $schema = app()->build(
      Schema::class, compact('table', 'callback')
    );

    $schema->run();
  }


  protected function addQuotes()
  {

  }


  protected function run()
  {
    $this->blue_print = new BluePrint( $this->table );

    $this->callback->call($this, $this->blue_print);

    $columns = $this->blue_print->getTable()->getColumns();

    $definitions = [];

    foreach ($columns as $column){

      $col_name = $column->getName();

      $col_type_length = $this->getTypeLengthClause( $column );

      $null_clause = $this->getNullClause( $column );

      $default_clause = $this->getDefaultClause( $column );

      pr(['usr' => __FUNCTION__, 'type name' => ($ct = $column->getType())->getName(),
          'type def_length' => $ct->getDefaultLength(),
          'length' => $column->getLength(), 'props' => $column->getProperties()]);

      $definition = "`{$col_name}` {$col_type_length} {$null_clause} {$default_clause}";

      $definitions[] = trim( Str::trimMultipleSpaces( $definition ) );

      pr(['usr' => __FUNCTION__, '$clause' => end($definitions)]);
    }

    // Add primary
    $definitions[] = $this->getPrimaryClause();


    $table_definition = implode(', ', $definitions);

    pr(['usr' => __FUNCTION__, '$table_definition' => $table_definition]);
  }


  protected function getPrimaryClause()
  {
    $primary = $this->table->resolvePrimary();

    return $primary ?  : '';
  }


  protected function getTypeLengthClause(Column $column)
  {
    $col_type = $column->getType()->getName();

    $length = ($n = $column->getLength()) ? "({$n})" : '';

    if($column instanceof NumericColumn && $column->getUnsigned()){

      $unsigned = ' ' . strtoupper(NumericColumn::UNSIGNED);
    }

    return $col_type . $length . ($unsigned ?? '');
  }


  protected function getNullClause(Column $column)
  {
    return ($column->getNull() ? '' : ' NOT') . ' NULL';
  }


  protected function getDefaultClause(Column $column)
  {
    if($column instanceof NumericColumn && $column->getAutoIncrement()){

      $default_clause = $this->getAutoIncrementClause($column);
    }
    else {
      $default_clause = $column->getHasDefault() ? $this->resolveDefaultValue($column) : '';
    }

    return $default_clause;
  }


  protected function getAutoIncrementClause(NumericColumn $column)
  {
    $column->setHasDefault(false);

    $this->table->setAutoIncrement($column);

    return ' ' . strtoupper(NumericColumn::AUTOINCREMENT);
  }


  protected function resolveDefaultValue(Column $column)
  {
    if($column instanceof DateTimeColumn){
      [$timestamp, $on_update] = $this->resolveDateTimeDefault( $column );
    }
    else {
      $timestamp = $on_update = '';
    }

    $default = $timestamp ?: $column->getDefault();

    switch(true){
      case $timestamp : $default_value = $default . ' ' . $on_update; break;

      case is_null( $default ) : $default_value = 'NULL ' . $on_update; break;

      default : $default_value = "'{$default}'";  // Use add_quotes_value()
    }

    return ' DEFAULT ' . $default_value;
  }


  /**
   * @param DateTimeColumn $column
   * @return string[]
   */
  protected function resolveDateTimeDefault(DateTimeColumn $column)
  {
    if( ! $column->isDateTime()){
      return ['', ''];
    }

    $current_timestamp = strtoupper(DateTimeColumn::CURRENT_TIMESTAMP);

    $precision = $column->getPrecision();

    $current_timestamp .= ((int) $precision > 0) ? "({$precision})" : '';

    $default_timestamp = $column->getCurrentTimestamp() ? $current_timestamp : '';

    $on_update = $column->getOnUpdate()
      ? str_replace('_', ' ', DateTimeColumn::ON_UPDATE)
      : '';

    $default_on_update = $on_update ? strtoupper($on_update) .' '. $current_timestamp : '';

    return [ $default_timestamp, $default_on_update ];
  }


}
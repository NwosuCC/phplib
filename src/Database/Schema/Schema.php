<?php

namespace Orcses\PhpLib\Database\Schema;


use Closure;
use Orcses\PhpLib\Database\Schema\Columns\Column;


class Schema
{
  protected $table;

  protected $callback;

  /** @var Column[] $columns */
  protected $columns = [];


  public function __construct(Table $table, Closure $callback)
  {
    $this->table = $table;

    $this->callback = $callback;
  }


  public static function create(string $table, callable $callback)
  {
    $table = static::getTable( $table );

//    static::Schema( $table, $callback );

    // --test
    return static::Schema( $table, $callback );
  }


  public static function table(string $table, callable $callback)
  {
    $table = static::getTable( $table )->setExists( true );

    static::Schema( $table, $callback );
  }


  protected static function getTable(string $table)
  {
    return app()->build(Table::class, ['name' => $table]);
  }


  protected static function Schema(Table $table, callable $callback)
  {
    $schema = app()->build(
      Schema::class, compact('table', 'callback')
    );

    return $schema->run();
  }


  protected function run()
  {
    $blue_print = new BluePrint( $this->table );

    $this->callback->call($this, $blue_print);

    $table = $blue_print->getTable();

    $cols = $blue_print->getColumns();

    pr(['usr' => __FUNCTION__, 'table' => $table, 'cols' => $cols]);

    $table_def = [];

    foreach ($cols as $col){
      $col_type = $col->getType();

      $length = ($n = $col->getLength()) ? "({$n})" : '';

      $null = ($col->getNull() ? '' : ' NOT') . ' NULL';

      $default_value = is_null($dv = $col->getDefault()) ? 'NULL' : $dv;

      $default = $col->getNoDefault() ? '' : ' DEFAULT ' . "'{$default_value}'";

      pr(['usr' => __FUNCTION__, 'type name' => $col_type->getName(), 'type def_length' => $col_type->getDefaultLength(),
        'length' => $col->getLength(), 'props' => $col->getProperties()]);

      $table_def[] = "`{$col->getName()}` {$col_type->getName()}{$length}{$null}{$default}";

      pr(['usr' => __FUNCTION__, '$clause' => end($table_def)]);
    }

    $table_definition = implode(', ', $table_def);

    pr(['usr' => __FUNCTION__, '$table_definition' => $table_definition]);
  }


}
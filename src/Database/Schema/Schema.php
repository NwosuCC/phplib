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
    return $this->callback->call($this, new BluePrint($this->table));
  }


}
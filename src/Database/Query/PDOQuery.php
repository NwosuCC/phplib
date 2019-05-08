<?php

namespace Orcses\PhpLib\Database\Query;


use Orcses\PhpLib\Interfaces\Connectible;


class PDOQuery
{
  protected $connection;

  /** The current database connected to */
  protected $database;

  /** Stores the tables in a selected database */
  protected $tables = [];

  /** A map of db.table to their definitions */
  protected static $definitions = [];

  /** Additional settings */
  protected $options = [];


  public function __construct(Connectible $connection)
  {
    $this->connection = $connection->connect();

    $this->database = $connection->getDatabase();

    $this->options = $connection->getOptions();
  }


  protected function addTable(string $table)
  {
    $this->tables[ $this->database ][] = $table;
  }


  protected function hasTable(string $table)
  {
    return array_key_exists($this->database, $this->tables)
        && in_array($table, $this->tables[ $this->database ]);
  }


  protected function addTableDefinition(string $table, $definitions)
  {
    static::$definitions[ $this->database ][ $table ] = $definitions;
  }


  protected function hasTableDefinition(string $table)
  {
    return ! empty(static::$definitions[ $this->database ][ $table ]);
  }


  protected function getTableDefinition(string $table)
  {
    return static::$definitions[ $this->database ][ $table ];
  }



}
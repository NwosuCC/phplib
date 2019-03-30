<?php

namespace Orcses\PhpLib\Database\Query;


use Orcses\PhpLib\Interfaces\Connectible;


class Query
{
  protected $connection;

  /**
   * Holds the new instance of the child class
   */
  protected static $query;

  /** The current database connected to */
  protected $database;

  /** Stores the tables in a selected database */
  protected $tables = [];

  /** A map of db.table to their definitions */
  protected $definitions = [];


  public function __construct(Connectible $connection) {

    $this->connection = $connection->connect();

    $this->database = $connection->getDatabase();

    static::$query = $this;
  }


  protected static function query() {
    // ToDo: ...
    return static::$query;
  }


  protected function addTable(string $table) {
    $this->tables[ $this->database ][] = $table;
  }


  protected function hasTable(string $table) {
    return in_array($table, $this->tables[ $this->database ]);
  }


  protected function addTableDefinition(string $table, $definitions) {
    $this->definitions[ $this->database ][ $table ] = $definitions;
  }


  protected function hasTableDefinition(string $table) {
    return ! empty($this->definitions[ $this->database ][ $table ]);
  }


  protected function getTableDefinition(string $table) {
    return $this->definitions[ $this->database ][ $table ];
  }



}
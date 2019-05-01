<?php

namespace Orcses\PhpLib\Exceptions\Database\Schema;


use RuntimeException;

class DuplicateColumnException extends RuntimeException
{

  public function __construct(string $column, string $table) {

    $message = "The column '{$column}' already exists on table '{$table}'";

    parent::__construct($message);
  }

}
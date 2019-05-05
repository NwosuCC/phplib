<?php

namespace Orcses\PhpLib\Exceptions\Database\Schema;


use RuntimeException;

class ColumnNotFoundException extends RuntimeException
{

  public function __construct($column, $table) {

    $message = "The column '{$column}' does not exist in table '{$table}'";

    parent::__construct($message);
  }

}
<?php

namespace Orcses\PhpLib\Exceptions\Database\Schema;


use RuntimeException;

class UnsupportedTablePropertyException extends RuntimeException
{

  public function __construct(string $name) {

    $message = "The table property '{$name}' is currently not supported'";

    parent::__construct($message);
  }

}
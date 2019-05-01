<?php

namespace Orcses\PhpLib\Exceptions\Database\Schema;


use RuntimeException;

class InvalidColumnPropertyException extends RuntimeException
{

  public function __construct($name ) {

    $message = "The property '{$name}' is not expected'";

    parent::__construct($message);
  }

}
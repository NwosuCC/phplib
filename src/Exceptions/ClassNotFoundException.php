<?php

namespace Orcses\PhpLib\Exceptions;


use RuntimeException;

class ClassNotFoundException extends RuntimeException
{

  public function __construct($name)
  {
    $message = "Class '{$name}' does not exist'";

    parent::__construct($message);
  }

}
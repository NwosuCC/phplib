<?php

namespace Orcses\PhpLib\Exceptions\Database\Schema;


use RuntimeException;

class InvalidColumnPropertyException extends RuntimeException
{

  public function __construct( $prop ) {

    $message = "The property '{$prop}' is not expected'";

    parent::__construct($message);
  }

}
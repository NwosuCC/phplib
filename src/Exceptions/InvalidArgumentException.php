<?php

namespace Orcses\PhpLib\Exceptions;


use RuntimeException;

class InvalidArgumentException extends RuntimeException
{

  public function __construct($argument, $function)
  {
    $message = "The argument '{$argument}' supplied to function '{$function}' is not valid'";

    parent::__construct($message);
  }

}
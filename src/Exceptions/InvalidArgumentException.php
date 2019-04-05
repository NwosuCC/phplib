<?php

namespace Orcses\PhpLib\Exceptions;


use RuntimeException;

class InvalidArgumentException extends RuntimeException
{

  public function __construct($argument, $function = null)
  {
    $message = func_num_args() === 1
      ? func_get_args()[0]
      : "The argument '{$argument}' supplied to function '{$function}' is not valid'";

    parent::__construct($message);
  }

}
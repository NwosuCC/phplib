<?php

namespace Orcses\PhpLib\Exceptions\Routes;


use RuntimeException;

class MethodNotSupportedException extends RuntimeException
{

  public function __construct(string $method)
  {
    $message = "Http request method '{$method}' is not supported'";

    parent::__construct($message);
  }

}
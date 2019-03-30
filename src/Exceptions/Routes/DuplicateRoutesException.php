<?php

namespace Orcses\PhpLib\Exceptions\Routes;


use RuntimeException;

class DuplicateRoutesException extends RuntimeException
{

  public function __construct(string $file, string $method, string $uri)
  {
    $message = "Route {$method}('{$uri}') is already registered in '{$file}.php'";

    parent::__construct($message);
  }

}
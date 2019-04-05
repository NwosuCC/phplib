<?php

namespace Orcses\PhpLib\Exceptions\Routes;


use RuntimeException;

class DuplicateRoutesException extends RuntimeException
{

  public function __construct(string $file, string $method = null, string $uri = null)
  {
    $message = func_num_args() === 1
      ? func_get_args()[0]
      : "Route {$method}('{$uri}') is already registered in '{$file}.php'";

    parent::__construct($message);
  }

}
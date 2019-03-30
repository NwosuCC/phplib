<?php

namespace Orcses\PhpLib\Exceptions\Routes;


use RuntimeException;

class DuplicateRouteNamesException extends RuntimeException
{

  public function __construct(string $name)
  {
    $message = "Route with name '{$name}' already exists";

    parent::__construct($message);
  }

}
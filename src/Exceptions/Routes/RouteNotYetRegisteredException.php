<?php

namespace Orcses\PhpLib\Exceptions\Routes;


use RuntimeException;

class RouteNotYetRegisteredException extends RuntimeException
{

  public function __construct(string $name)
  {
    $message = "There is no registered route to assign name '{$name}'";

    parent::__construct($message);
  }

}
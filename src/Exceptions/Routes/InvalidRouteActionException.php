<?php

namespace Orcses\PhpLib\Exceptions\Routes;


use RuntimeException;

class InvalidRouteActionException extends RuntimeException
{

  public function __construct($name, $route = null)
  {
    $message = $route
      ? "Action for route '{$route}' could not be loaded"
      : "Route action '{$name}' not found";

    parent::__construct($message);
  }

}
<?php

namespace Orcses\PhpLib\Exceptions\Base;


use RuntimeException;

class MethodNotFoundException extends RuntimeException
{

  public function __construct($name, $class = null)
  {
    $message = "Method '{$name}' does not exist" . ($class ? " in class {$class}" : '');

    parent::__construct($message);
  }

}
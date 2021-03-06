<?php

namespace Orcses\PhpLib\Exceptions\Database;


use RuntimeException;

class ConnectionNotFoundException extends RuntimeException
{

  public function __construct( $name ) {

    $message = "The connection '{$name}' may not have been registered";

    parent::__construct($message);

  }

}
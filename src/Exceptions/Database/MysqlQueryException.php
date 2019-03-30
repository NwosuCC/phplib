<?php

namespace Orcses\PhpLib\Exceptions\Database;


use RuntimeException;

class MysqlQueryException extends RuntimeException
{

  public function __construct( $message, $func_name = '' ) {

    if($func_name){
      $message = "MysqlQuery::{$func_name}() " . $message;
    }

    parent::__construct($message);

  }

}
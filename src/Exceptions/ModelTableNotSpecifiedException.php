<?php

namespace Orcses\PhpLib\Exceptions;


use RuntimeException;

class ModelTableNotSpecifiedException extends RuntimeException
{

  public function __construct( $model_name ) {

    $name = strtolower($model_name);

    $message = "Since the database has no table that corresponds with the model name '{$name}', "
             . "you need to explicitly specify the '\$table' property in the {$model_name} model";

    parent::__construct($message);

  }

}
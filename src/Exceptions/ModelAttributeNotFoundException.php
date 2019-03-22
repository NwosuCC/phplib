<?php

namespace Orcses\PhpLib\Exceptions;


use RuntimeException;

class ModelAttributeNotFoundException extends RuntimeException
{

  public function __construct( $model_name, $attribute ) {

    $message = "The attribute '{$attribute}' is not defined in the model '{$model_name}'";

    parent::__construct($message);

  }

}
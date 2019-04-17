<?php

namespace Orcses\PhpLib\Exceptions\Files;


use RuntimeException;

class InvalidFileException extends RuntimeException
{

  public function __construct($file_type, $file_name = '')
  {
    $message = func_num_args() === 1
      ? func_get_args()[0]
      : "File {$file_name} is invalid as '{$file_type}'";

    parent::__construct($message);
  }


}
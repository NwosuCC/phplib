<?php

namespace Orcses\PhpLib\Exceptions;


use RuntimeException;

class FileNotFoundException extends RuntimeException
{

  public function __construct($file_type, $failed_path = '')
  {
    $message = func_num_args() === 1
      ? func_get_args()[0]
      : "'{$file_type}' file not found in {$failed_path}";

    parent::__construct($message);
  }


}
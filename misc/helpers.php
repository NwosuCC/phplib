<?php
if (! function_exists('requires')) {
  /**
   * Ensures all required constants are defined
   *
   * @param  array  $required_constants
   * @throws Exception
   */
  function requires($required_constants)
  {
    $missing = array_filter($required_constants, function ($param){
      return !defined($param);
    });

    if($missing) {
      throw new Exception("Undefined Logger parameters: " . implode(', ', $missing));
    }
  }
}
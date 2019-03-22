<?php

namespace Orcses\PhpLib\Traits;

use Exception;


trait HandlesError
{
  /**
   * If true (default), any Exception will be thrown for errors in the using class
   */
  private static $show_errors = true;


  /**
   * Holds the error message once an error is encountered
   */
  private static $error_message = '';


  /**
   * Holds the custom callback functions supplied to handle errors
   * Structure:
   *  [
   *    'function' => (callable e.g 'in_array', [Auth::class, 'logout'], etc),
   *    'parameters' => (the arguments to pass to the callable e.g ['key', true], etc)
   *  ]
   */
  private static $callback = [];


  public static function registerErrorHandler(array $callback = []) {
    HandlesError::hideErrors( $callback );
  }


  /**
   * Suppress Exceptions from this class and instead handle errors using a custom callback
   *
   * @param array $callback The callback function to pass the error to
   */
  protected static function hideErrors(array $callback = []) {
    static::$show_errors = false;

    static::$callback = [];

    // Extract $callback values
    $function = $callback['function'] ?? $callback[0] ?? null;

    $parameters = $callback['parameters'] ?? $callback[1] ?? [];

    // If callable, store the $callback values for future call in static::throwError()
    if(is_callable($function)){
      static::$callback = [
        'function' => $function, 'parameters' => $parameters
      ];
    }
  }


  /**
   * Returns the error message with the class that has the error
   *
   * @return string
   */
  protected static function getErrorMessage(){
    if($error_message = static::$error_message){
      static::$error_message = '';

      return get_class() . ': ' . $error_message;
    }

    return '';
  }


  /**
   * Throws any Exception OR calls the custom error handler if provided, then, exits the application
   *
   * @param string $message The error message
   * @throws Exception
   * @return mixed
   */
  protected static function throwError(string $message){
    static::$error_message = $message;

    if(static::$show_errors){
      throw new Exception(static::getErrorMessage());

    }
    else if(static::$callback){
      try {
        return call_user_func_array(
          static::$callback['function'], static::$callback['parameters']
        );
      }
      catch (Exception $e) {}
    }

    exit();
  }

}
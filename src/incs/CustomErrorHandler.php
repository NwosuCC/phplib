<?php

namespace Orcses\PhpLib\Incs;


interface CustomErrorHandler
{
  /**
   * Registers the callback for custom error handling
   *
   * In the implementing class, you can do some stuff before and/or after register the ErrorHandler e.g
   *    - require additional $callback parameters
   *    - implement other error handling options, etc
   *
   * @param array $callback
   */
  static function registerErrorHandler(array $callback = []);

}
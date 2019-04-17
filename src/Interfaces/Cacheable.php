<?php

namespace Orcses\PhpLib\Interfaces;


interface Cacheable
{
  /**
   * Retrieves the value cached using the specified key
   *
   * @param string  $key
   * @return string
   */
  public static function get(string $key);


  /**
   * Stores the value using the specified key
   *
   * @param string  $key
   * @param string  $value
   * @param int     $expiration
   * @return string
   */
  public static function store(string $key, string $value, int $expiration = 0);


  /**
   * Expires the stored token via its key
   *
   * @param string  $key
   * @return string
   */
  public static function expire(string $key);


}
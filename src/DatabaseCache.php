<?php

namespace Orcses\PhpLib;

use InvalidArgumentException;


class DatabaseCache
{
  /**
   * Retrieves the value cached using the specified key
   *
   * @param string  $key
   * @return string
   */
  public static function fetch(string $key){
    $table = 'cache';

    $columns = ['key', 'value', 'expiration'];

    $where = [
      'key' => $key, 'expiration' => ['>', time()]
    ];

    $cache = Queries::select($table, $columns, $where)->first();

    return $cache['value'] ?? null;
  }

  public static function store(string $key, string $value, int $expiration = 0){
    $table = 'cache';

    if($expiration < 0){
      throw new InvalidArgumentException('int $expiration must be zero or a positive integer');
    }
    else if($expiration === 0){
      // Add 1 year to current Unix timestamp to mimic Infinity
      $y_1 = (int) (60 * 60 * 24 * 365.25 * 1);
      $expiration = time() + $y_1;
    }

    $update_values = [
      'key' => $key, 'value' => $value, 'expiration' => $expiration
    ];

    $where = ['key' => $key];

    return Queries::update_new($table, $update_values, $where)
      or Queries::insert_check_new($table, $update_values, $where);
  }
}
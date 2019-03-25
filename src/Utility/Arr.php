<?php

namespace Orcses\PhpLib\Utility;


class Arr
{
  /**
   * Removes empty values from an array
   * @param array $values
   * @return array
   */
  public static function stripEmpty(array $values){
    return array_filter($values, function ($val){
      if(is_string($val) || is_numeric($val)){ $val = trim($val); }
      return $val !== '';
    });
  }


  /**
   * Pads the given array to the specified size
   * @param array $array
   * @param int $pad_size Only absolute values
   * @param int|string $pad_value
   * @return array
   */
  public static function pad(array $array, int $pad_size, $pad_value){
    return array_pad($array, $pad_size, $pad_value);
  }
  //    $columns = Arr::each($columns, [Arr::class, 'pad'], 2, '');


  /**
   * Returns a part of the array specified in the @param $keys
   * Can replace the old keys with new keys
   *
   * @param array $keys The to get/remove.
   * Each $keys item can be a string (e.g 'name') or an array(e.g ['status', 'active'])
   * E.g $keys = [ 'name', ['status', 'active'] ]
   * For array $keys items, the function replaces old key 'status' with new key 'active' in the returned array
   *
   * @param array $array
   * @param bool $remove If true, removes values having the keys specified in $keys and returns the rest
   * @param bool $assoc If true (default), returns an associative array, else, returns indexed array
   * @return array
   */
  public static function pick(array $array, array $keys, bool $remove = false, bool $assoc = true)
  {
    $array = (array) $array;
    $keys = array_values($keys);

    $old_keys = [];

    if($remove){
      $keys = $old_keys = array_diff( array_keys($array), $keys);
    }
    else {
      foreach($keys as $i => $value){
        if(is_array($value)){
          list($value, $keys[ $i ]) = [ key($value), current($value) ];
        }

        $old_keys[ $i ] = $value;
      }
    }

    $assoc_array_values = [];
    $index_array_values = [];

    foreach($keys as $i => $value){
      $new_key = $keys[ $i ];
      $old_key = $old_keys[ $i ];
      $assoc_array_values[ $new_key ] = $index_array_values[] = $array[ $old_key ];
    }

    return ($assoc) ? $assoc_array_values : $index_array_values;
  }


  public static function pickOnly(array $array, array $keys, $assoc = true){
    return Arr::pick($array, $keys, false, $assoc);
  }


  public static function pickExcept(array $array, array $keys, $assoc = true){
    return Arr::pick($array, $keys,true, $assoc);
  }


  public static function each(array $values, $callback, ...$arguments){

    return array_map(function($value) use ($callback, $arguments){

      return call_user_func($callback, $value, ...$arguments);

    }, $values);
  }


  /**
   * Removes any outer array wraps in a multi-dimensional array
   * @param $array
   * @return array
   */
  public static function unwrap(array $array){
    if(count($array) === 1 && isset($array[0]) && is_array($array[0])){
      $array =  Arr::unwrap($array[0]);
    }

    return $array;
  }


  public static function where(array $array, callable $where){
    return array_filter($array, function ($value) use($where){
      return call_user_func($where, $value);
    });
  }


  /**
   * Returns true if the array is indexed array
   * @param $array
   * @return bool
   */
  public static function isIndexedArray(array $array){
    $array_keys = array_keys($array);

    $numeric_keys = static::where( $array_keys, 'is_numeric');

    return count($numeric_keys) === count($array_keys);
  }


  public static function toDotNotation(array $values){
    $flat_array = [];

    foreach($values as $key => $value){
      if(is_array($value) && ! static::isIndexedArray($value)){
        foreach(static::toDotNotation($value) as $last_key => $last_value){
          $flat_array[ $key.'.'.$last_key ] = $last_value;
        }
      }
      else {
        $flat_array[ $key ] = $value;
      }
    }

    return $flat_array;
  }


  public static function get(array $array, $key){
    return arr_get($array, $key);
  }


}


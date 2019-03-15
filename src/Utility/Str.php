<?php

namespace Orcses\PhpLib\Utility;


class Str
{
  /**
   * E.g given " w.status  w.id ", returns ['w.status', 'w.id']
   * @param string $value The string to split and trim
   * @param string $delimiter The delimiter character to use for the split
   * @return array
   */
  public static function splitByChar(string $value, string $delimiter){
    $parts = array_map(function($str){ return trim($str); }, explode($delimiter, $value));

    return array_filter($parts, function($str){ return $str !== ''; });
  }


  /**
   * Strips trailing character and returns the new value
   * @param string $value
   * @param string $char  The character to remove
   * @return string
   */
  public static function stripTrailingChar($value, $char) {
    $has_char = stripos($value, $char) !== false;
    $ends_with_char = strlen( stristr($value, $char)) === strlen($char);

    if($has_char && $ends_with_char){
      $value = stristr($value, $char, true);
    }

    return $value;
  }

  /**
   * Sanitizes a string as SQL query
   * @param $string
   * @return string
   */
  public static function clean($string){
    return trim( htmlspecialchars( stripslashes($string)));
  }


  /**
   * Sanitizes a string as SQL query
   * @param string $string
   * @param int $start
   * @param int $length
   * @return string
   */
  public static function hash($string = '', int $length = 0, int $start = 0){
    $hash = sha1($string.microtime(true));

    return $length ? substr($hash, $start, $length) : $hash;
  }


  /**
   * Creates a relatively strong password hash from a string
   * @param string $string
   * @return string
   */
  public static function hashedPassword($string){
    $salt_1  = static::hash( $string, 1, 22);
    $crypt_1 =  crypt($string, '$2a$09$'.$salt_1.'$');

    $salt_2  = static::hash( $string, 18, 22);
    $crypt_2 =  crypt($string, '$2a$09$'.$salt_2.'$');

    $double_crypt = substr($crypt_1, -15) . substr($crypt_2, -17);
    $crypt_BlowFish_salt = substr($double_crypt, 7, 22);

    return crypt($string, '$2a$09$'.$crypt_BlowFish_salt.'$');
  }


  public static function exists_id($table, $column_name, $id, $status = ''){
    list($column_name, $id) = Queries::escape([$column_name, $id]);

    $where = "WHERE $column_name = '$id'";

    if($status !== ''){
      $where .= ($status !== '0') ? " AND status = 1" : " AND status BETWEEN 1 AND 2";
    }

    return Queries::select($table, '', $where)->to_array();
  }


}


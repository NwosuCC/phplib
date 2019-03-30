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
   * Checks for a trailing character and returns true if found, false if not found
   * @param string $value
   * @param string $char  The character to remove
   * @return string
   */
  public static function hasTrailingChar($value, $char) {
    $has_char = stripos($value, $char) !== false;
    $ends_with_char = strlen( stristr($value, $char)) === strlen($char);

    return ($has_char && $ends_with_char);
  }


  /**
   * Strips trailing character and returns the new value
   * @param string $value
   * @param string $char  The character to remove
   * @return string
   */
  public static function stripTrailingChar($value, $char) {
    if(static::hasTrailingChar($value, $char)){
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


  public static function trimChars(string $value, string $char, int $chunk = 0){
    // ToDo: include escape characters

    $n = $chunk + 1;

    $regex = '/['.$char.']{'.$n.',}/';

    $replace = str_repeat($char, $chunk);

    return preg_replace($regex, $replace, $value);
  }


  public static function trimMultipleChars(string $value, string $char){
    return static::trimChars($value, $char, 1);
  }


  public static function trimSpaces(string $value, int $chunk = 0){
    return static::trimChars($value, ' ', $chunk);
  }


  public static function trimMultipleSpaces(string $value){
    return static::trimSpaces($value, 1);
  }


  public static function snakeCase(string $value){
    $split = str_split( static::trimMultipleSpaces($value));

    foreach($split as $i => $char){
      if(strtoupper($char) === $char){
        $split[ $i ] = ($i > 0 ? '_' : '') . strtolower($char);
      }
    }

    return implode('', $split);
  }


  public static function titleCase(string $value){
    return ucfirst( static::camelCase($value) );
  }


  public static function camelCase(string $value){
    $split = str_split( static::trimMultipleSpaces($value));

    $cap_next = false;

    foreach($split as $i => $char){
      if(preg_match("/[^A-Za-z0-9]/", $char)) {
        $split[$i] = '';

        $cap_next = true;
      }
      else {
        $split[ $i ] = $cap_next ? strtoupper($char) : strtolower($char);

        $cap_next = false;
      }
    }

    return implode('', $split);
  }


  public static function matchCase(string $case, string $value){
    $case = trim($case);

    $supported_cases = [
      'strtoupper', 'strtolower', 'title_case'
    ];

    $match = false;

    foreach ($supported_cases as $function){
      if( ! in_array($case, $supported_cases)){
        continue;
      }

      $match = call_user_func($function, $value) === call_user_func($case, $value);
    }

    return $match;
  }


}


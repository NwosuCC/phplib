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


  public static function trimNonSpaceChar(string $value, string $char){
    return (stripos($char, ' ') === false) ? trim($value) : $value;
  }


  /**
   * Checks for a trailing character and returns true if found, false if not found
   * @param string $value
   * @param string $char  The character to remove
   * @return string
   */
  public static function hasTrailingChar($value, $char)
  {
    $value = static::trimNonSpaceChar($value, $char);

    return (substr($value, - strlen($char)) === $char) ? $value : false;
  }


  /**
   * Strips trailing character and returns the new value
   * @param string $value
   * @param string $char  The character to remove
   * @return string
   */
  public static function stripTrailingChar($value, $char)
  {
    if($trimmed_value = static::hasTrailingChar($value, $char)){
      $value = substr($trimmed_value, 0, - strlen($char));
    }

    return $value;
  }


  public static function hasLeadingChar($value, $char)
  {
    $value = static::trimNonSpaceChar($value, $char);

    return stripos($value, $char) === 0 ? $value : false;
  }


  public static function stripLeadingChar(string $value, string $char)
  {
    if($trimmed_value = static::hasLeadingChar($value, $char)){
      $value = substr($trimmed_value, strlen($char));
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
    // ToDo: refactor - currently produces inconsistent passwords
    /*$salt_1  = static::hash( $string, 1, 22);
    $crypt_1 =  crypt($string, '$2a$09$'.$salt_1.'$');

    $salt_2  = static::hash( $string, 18, 22);
    $crypt_2 =  crypt($string, '$2a$09$'.$salt_2.'$');

    $double_crypt = substr($crypt_1, -15) . substr($crypt_2, -17);
    $crypt_BlowFish_salt = substr($double_crypt, 7, 22);

    return crypt($string, '$2a$09$'.$crypt_BlowFish_salt.'$');*/

    return md5( sha1($string));
  }


  public static function trimToNthChars(string $value, string $char, int $n = 0){
    // ToDo: include escape characters

    $a = $n + 1;

    $regex = '/['.$char.']{'.$a.',}/';

    $replace = str_repeat($char, $n);

    return preg_replace($regex, $replace, $value);
  }


  public static function trimMultipleChars(string $value, string $char){
    return static::trimToNthChars($value, $char, 1);
  }


  public static function trimToNthSpaces(string $value, int $n = 0){
    return static::trimToNthChars($value, ' ', $n);
  }


  public static function trimMultipleSpaces(string $value){
    return static::trimToNthSpaces($value, 1);
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


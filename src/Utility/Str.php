<?php

namespace Orcses\PhpLib\Utility;


class Str
{
  protected static $non_printable_chars = [];

  protected static $error;


  public static function getError()
  {
    $error = static::$error;

    return (static::$error = null) ?: $error;
  }


  /**
   * @param       $number
   * @param int   $precision
   * @param bool  $round_up
   * @return int | float | double
   */
  public static function roundNumber($number, int $precision = 2, bool $round_up = true)
  {
    $round_flag = $round_up ? PHP_ROUND_HALF_UP : PHP_ROUND_HALF_DOWN;

    return round( $number, $precision, $round_flag);
  }


  public static function currency($number, array $options = null)
  {
    $precision = $options['precision'] ?? 2;

    $round_up = (bool) ($options['round_up'] ?? false);

    return static::roundNumber( $number, $precision, $round_up);
  }


  public static function getNonPrintableChars()
  {
    if( ! static::$non_printable_chars){
      static::$non_printable_chars = array_map('chr', range(0, 31));
    }

//    combo JS: return String( text ).replace(/[^ -~]+/g, "");

    return static::$non_printable_chars;
  }


  /**
   * Catches any single quote that is NOT already escaped
   * @param string $value
   * @param string $char
   * @return bool
   */
  public static function hasUnescapedSingleQuote($value, $char)
  {
    $quote_index = strpos($value, $char);
    $slash_index = strpos($value,"\\");

    $has_quote = $quote_index !== false;
    $quote_is_unescaped = ($quote_index - 1) !== $slash_index;

    return $has_quote && $quote_is_unescaped;
  }


  protected static function addQuotes($values, string $char)
  {
    $single_quote = "{$char}";

    $is_array = is_array($values);

    $new_values =  array_map(function($value) use($single_quote){

      return "{$single_quote}". $value ."{$single_quote}";

    }, (array) $values);

    return $is_array ? $new_values : $new_values[0];
  }


  public static function addSingleQuotes($values)
  {
    return static::addQuotes( $values, "'" );
  }


  public static function addBackQuotes($values)
  {
    return static::addQuotes( $values, "`" );
  }


  /**
   * E.g given " w.status  w.id ", returns ['w.status', 'w.id']
   * @param string $value The string to split and trim
   * @param string $delimiter The delimiter character to use for the split
   * @return array
   */
  public static function splitByChar(string $value, string $delimiter)
  {
    $parts = array_map(function($str){ return trim($str); }, explode($delimiter, $value));

    return array_filter($parts, function($str){ return $str !== ''; });
  }


  /**
   * Trims and returns the value iff the supplied $char does not contain white-space
   * @param string $value
   * @param string $char
   * @return string
   */
  public static function trimNonSpaceChar(string $value, string $char)
  {
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
  public static function clean($string)
  {
    return trim( htmlspecialchars( stripslashes($string)));
  }


  public static function stripNonPrintableChars(string $value)
  {
    $remove_chars = static::getNonPrintableChars();

    return str_replace($remove_chars, '', $value);
  }


  public static function safeJsonDecode(string $value, bool $assoc = true)
  {
    $sanitized_contents = Str::stripNonPrintableChars( $value );

    $decoded_contents = json_decode( $sanitized_contents, $assoc);

    if(json_last_error() === JSON_ERROR_NONE){
      return $decoded_contents;
    }

    static::$error = json_last_error_msg();

    return null;
  }


  /**
   * Hashes a string using 'sha1'
   *
   * @param string $string
   * @param int $start
   * @param int $length
   * @param bool $random
   * @return string
   */
  public static function hash($string = '', int $length = 0, int $start = 0, bool $random = false)
  {
    $hash = sha1( $string . ($random ? microtime(true) : '') );

    return $length ? substr($hash, $start, $length) : $hash;
  }


  public static function randomHash($string = '', int $length = 0, int $start = 0)
  {
    return static::hash($string, $length, $start, true);
  }


  /**
   * Creates a relatively strong password hash from a string
   * @param string $string
   * @return string
   */
  public static function hashedPassword($string)
  {
    return password_hash($string, PASSWORD_DEFAULT);
  }


  public static function trimToNthChars(string $value, string $char, int $n = 0)
  {
    // ToDo: include escape characters

    $a = $n + 1;

    $regex = '/['.$char.']{'.$a.',}/';

    $replace = str_repeat($char, $n);

    return preg_replace($regex, $replace, $value);
  }


  public static function trimMultipleChars(string $value, string $char)
  {
    return static::trimToNthChars($value, $char, 1);
  }


  public static function trimToNthSpaces(string $value, int $n = 0)
  {
    return static::trimToNthChars($value, ' ', $n);
  }


  public static function trimMultipleSpaces(string $value)
  {
    return static::trimToNthSpaces($value, 1);
  }


  public static function replaces(string $subject, array $replaces)
  {
    foreach ($replaces as $find => $replace){

      $subject = str_replace('{'.$find.'}', $replace, $subject);
    }

    return $subject;
  }


  public static function snakeCase(string $value)
  {
    $split = str_split( static::trimMultipleSpaces($value));

    foreach($split as $i => $char){
      if(strtoupper($char) === $char){
        $split[ $i ] = ($i > 0 ? '_' : '') . strtolower($char);
      }
    }

    return implode('', $split);
  }


  public static function titleCase(string $value)
  {
    return ucfirst( static::camelCase($value) );
  }


  public static function camelCase(string $value)
  {
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


  public static function matchCase(string $case, string $value)
  {
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


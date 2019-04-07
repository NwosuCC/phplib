<?php

namespace Orcses\PhpLib;


use Orcses\PhpLib\Interfaces\HandlesErrors;
use Orcses\PhpLib\Traits\HandlesError;


class Validator implements HandlesErrors
{

  use HandlesError;


  protected static $error_handler;

  const REQUIRED = 'required';

  // [Function name, [Argument variables names]]
  protected static $rules = [
    self::REQUIRED => [],
    'in' => [],
    'alphanumeric' => [],
    'alphanumeric_space' => [],
    'alphanumeric_chars' => [],
    'email' => [],
    'confirmed' => [],
    'length' => [],
    'min' => [],
    'max' => [],
    'adr' => ['address'],
    'xtr' => ['extraFields'],
    'chk' => ['checkbox', ['required', 'defaultValue']],
    'txt' => ['text'],
    'url' => ['url'],
    'fil' => ['filePath'],
    'nuf' => ['numberFormat', ['way', 'toFixed']],
    'cnf' => ['currencyNumberFormat', ['way']],
    'bnf' => ['bankNumberFormat', ['type']],
    'pnf' => ['phoneNumberFormat'],
    'dts' => ['datePickerDate_toTimestamp'],
    'fcn' => ['firstCharacterNotNumber'],
  ];


  protected $post, $validator;

  protected $check_group = [];

  protected $failed_rule, $errors = [], $replaces = [];

  protected static $messages = [
    'alphanumeric' => "{field} may contain only letters and numbers",
    'alphanumeric_chars' => "{field} may contain only letters and numbers {more_info}",
    'alphanumeric_space' => "{field} may contain only letters and numbers {more_info}",
    'confirmed' => '{field} confirmation: Entries do not match',
    'email' => 'Valid {field} is required.',
    'length' => "{field} must be exactly {length} characters long",
    'max' => "{field} must not be more than {max} characters long",
    'min' => "{field} must not be less than {min} characters long",
    'password' => '{field} must have at least {more_info}',
    'required' => '{field} is required',
  ];


  // See CustomErrorHandler::setErrorHandler() for more info
  public static function setErrorHandler(array $callback = [])
  {
    static::$error_handler = $callback;
  }

  // See CustomErrorHandler::getErrorHandler() for more info
  public static function getErrorHandler()
  {
    return static::$error_handler;
  }


  public static function clean($string)
  {
    return stripslashes( htmlentities( trim($string)));
  }


  protected function getValidatorParams($key){
    if(is_callable($validator = $this->validator[ $key ])){
      $validator = call_user_func($validator, $this->post);
    }

    $rules = explode('|', $validator['check']);

    foreach ($rules as &$rule){
      if(stristr($rule, ':')){
        $rule = explode(':', $rule);
      }
    }

    $field = isset($validator['as']) ? $validator['as'] : null;
    pr(['usr' => __FUNCTION__, '$key' => $key, '$validator' => $validator, '$rules' => $rules, '$field' => $field]);

    return [$rules, $field];
  }


  protected function composeReport($failed_rule){
    $report = static::$messages[ $failed_rule ];

    foreach ($this->replaces as $find => $replace){
      $report = str_replace('{'.$find.'}', $replace, $report);
    }

    $this->failed_rule = null;
    $this->replaces = [];

    return $report;
  }


  protected function applyRule($rule, $key, $value, $field = ''){
    if( ! $field){
      $field = ucfirst($key);
    }

    list($function, $arguments) = is_array($rule) ? $rule : [$rule, null];

    if ($function !== 'password') {
      $value = static::clean($value);
    }

    call_user_func([static::class, $function], $value, $arguments);

    if($error = $this->failed_rule) {
      $this->replaces['field'] = $field;

      $report = static::composeReport($function);

      $this->errors[] = ['field' => $key, 'text' => $report];
    }

    return ! $error;
  }


  public function run($post, $validator){
    $this->post = $post;
    $this->validator = $validator;

    $checkedFields = [];

    foreach ($this->post as $key => $value) {
      $still_valid = true;

      if (array_key_exists($key, $this->validator)) {
        list($rules, $field) = $this->getValidatorParams($key);

        foreach ($rules as $rule){
          $rule_name = is_array($rule) ? $rule[0] : $rule;

          if (array_key_exists($rule_name, static::$rules)) {
            $checkedFields[] = $key;

            $valid = $this->applyRule($rule, $key, $value, $field);

            // Once there is an error, $still_valid = false
            if( ! $valid && $still_valid){
              $still_valid = false;
            }
          }
        }
      }
    }

    if ($omittedFields = array_diff( array_keys($validator), $checkedFields)) {
      foreach ($omittedFields as $key) {
        $this->applyRule(self::REQUIRED, $key, '');
      }
    }

    return $this->errors;
  }


  protected static function escape_chars($chars){
    $escaped_chars = [
      '\\', '/', '.', '+', '-', '*', '?', '[', ']', '(', ')', '{', '}', ':'
    ];

    foreach ($chars as $i => $char){
      if($key = array_search($char, $escaped_chars)){
        $chars[ $i ] = "\\".$escaped_chars[ $key ];
      }
    }

    return $chars;
  }


  protected static function get_charsLiterals(array $chars){
    $chars = array_unique($chars);

    $characters = [
      'dot' => '.', 'comma' => ',', 'hyphen' => '-', 'underscore' => '_', 'colon' => ':',
      'semi-colon' => ';', 'plus' => '+', 'equals' => '=', 'exclamation' => '!', 'spaces' => ' ',
      'at' => '@', 'hash' => '#', 'dollar' => '$', 'percent' => '%', 'ampersand' => '&', 'pipe' => '|',
      'asterisk' => '*', 'less_than' => '<', 'greater_than' => '>', 'question_mark' => '?',
      'forward_slash' => '/', 'back_slash' => '\\', 'single_quote' => '\'', 'double_quote' => '"',
    ];

    $paired_characters = [
      'parentheses' => ['(',')'],
      'brackets' => ['[',']'],
      'braces' => ['{','}'],
    ];

    $doubles = [];

    foreach ($paired_characters as $pair){
      $doubles = array_merge($doubles, $pair);
    }

    $chars_names = [];
    $chars_count = count($chars);
    $index = 0;

    foreach ($chars as $i => $char){
      if( ! $name = array_search($char, $characters)){
        if(in_array($char, $doubles)){
          foreach ($paired_characters as $key => $values){
            if(in_array($char, $values)){
              $name = $key;
              break;
            }
          }
        }
      }

      if($name){
        $chars_names[ $index ] = $name;

        if(($chars_count > 1) and ($i === ($chars_count - 1))){
          $chars_names[ $index ] = 'or ' . $chars_names[ $index ];
        }

        $index++;
      }
    }

    if(!empty($chars_names)){
      $chars_names = implode(', ', $chars_names);
    }

    return $chars_names;
  }


  /*==================================================================================
   |   V A L I D A T I O N   F U N C T I O N S  -  Arranged in alphabetical order
   *------------------------------------------------------------------------------ */

  public function alphanumeric($string)
  {
    return $this->alphanumeric_chars($string);
  }


  public function alphanumeric_chars($string, $chars = [])
  {
    // Unicode for all characters support
    if(is_array($esc_chars = static::escape_chars($chars))){
      $esc_chars  = implode(',', $esc_chars);
    }

    // ToDo: Try and return, instead, the chars that are NOT allowed but found in the $string

    $valid = ($string === '' or preg_match("/^[\p{L}\p{N}$esc_chars]+$/", $string));

    if( ! $valid){
      $this->failed_rule = true;

      $chars_names = static::get_charsLiterals($chars);

      if($chars){
        $this->replaces = [
          'more_info' => ", plus (optional) $chars_names"
        ];
      }
    }

    return ($valid) ? $string : null;
  }


  public function alphanumeric_space($string)
  {
    $allowedChars = [' '];

    return $this->alphanumeric_chars($string, $allowedChars);
  }


  public function confirmed($value, $confirm_value)
  {
    if(empty($value) or empty($confirm_value) or $value !== $confirm_value){
      $this->failed_rule = true;
    }

    return ( ! $this->failed_rule) ? $value : null;
  }


  public function contains(string $string, $arguments = [])
  {
    $replaces = [];

    foreach($arguments as $argument){
      switch ($argument){
        case 'letter' : {
          if( ! preg_match('/[A-Za-z]/', $string)){
            $replaces['letter'] = "one letter";
          }
          break;
        }
        case 'upper' : {
          if( ! preg_match('/[A-Z]/', $string)){
            $replaces['upper'] = "one upper-case letter";
          }
          break;
        }
        case 'lower' : {
          if( ! preg_match('/[a-z]/', $string)){
            $replaces['lower'] = "one lower-case letter";
          }
          break;
        }
        case 'digit' : {
          if( ! preg_match('/[0-9]/', $string)){
            $replaces['digit'] = "one digit";
          }
          break;
        }
        case 'char' : {
          if( ! preg_match('/[^A-Za-z0-9]/', $string)){
            $replaces['char'] = "one special character";
          }
          break;
        }
      }
    }

    return $replaces;
  }


  public function email($string)
  {
    $allowedChars = ['_', '@', '.'];

    if( ! $string = $this->alphanumeric_chars($string, $allowedChars)){
      return null;
    }

    if(filter_var($string, FILTER_VALIDATE_EMAIL) === false){
      return null;
    }

    return $string;
  }


  public function length($string, int $length)
  {
    $string = trim($string);

    if($this->failed_rule = strlen($string) !== $length){
      $this->replaces['length'] = $length;
    }

    return ( ! $this->failed_rule) ? $string : null;
  }


  public function max($string, int $max)
  {
    $string = trim($string);

    if($this->failed_rule = strlen($string) > $max){
      $this->replaces['max'] = $max;
    }

    return ( ! $this->failed_rule) ? $string : null;
  }


  public function min($string, int $min)
  {
    $string = trim($string);

    if($this->failed_rule = strlen($string) < $min){
      $this->replaces['min'] = $min;
    }

    return ( ! $this->failed_rule) ? $string : null;
  }


  public function password($password, $arguments = [])
  {
    $replaces = $this->contains($password, $arguments);

    if(empty($replaces)) {
      return $password;
    }
    else {
      if( (!empty($replaces['upper']) or !empty($replaces['lower'])) and !empty($replaces['letter']) ){
        unset( $replaces['letter'] );
      }

      $replaces = array_values( $replaces );
      $count = count($replaces);

      if($count > 1){
        $replaces[ $count - 1 ] = 'and ' . $replaces[ $count - 1 ];
      }

      $this->failed_rule = true;
      $this->replaces['more_info'] = implode(', ', $replaces);

      return null;
    }
  }


  public function required($value)
  {
    $value = trim($value);

    $this->failed_rule = ($value === '');

    return ( ! $this->failed_rule) ? $value : null;
  }



}
<?php

namespace Orcses\PhpLib;


use Orcses\PhpLib\Exceptions\InvalidArgumentException;
use Orcses\PhpLib\Interfaces\HandlesErrors;
use Orcses\PhpLib\Traits\HandlesError;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Utility\Str;


class Validator implements HandlesErrors
{

  use HandlesError;


  protected static $error_handler;

  const REQUIRED = 'required';

  // [Function name, [Argument variables names]]
  // ToDO: merge rules and messages [rule => message]
  protected static $rules = [
    self::REQUIRED => [],
    'address' => [],
    'alphanumeric' => [],
    'alphanumeric_chars' => [],
    'alphanumeric_space' => [],
    'confirmed' => [],
    'dimensions' => [],
    'email' => [],
    'fileType' => [],
    'image' => [],
    'in' => [],
    'length' => [],
    'max' => [],
    'maxHeight' => [],
    'maxLength' => [],
    'maxWidth' => [],
    'min' => [],
    'minHeight' => [],
    'minLength' => [],
    'minWidth' => [],
    // ToDO: import from old
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
    'address' => "{field} may contain only letters and numbers{more_info}",
    'alphanumeric' => "{field} may contain only letters and numbers",
    'alphanumeric_chars' => "{field} may contain only letters and numbers{more_info}",
    'alphanumeric_space' => "{field} may contain only letters and numbers{more_info}",
    'confirmed' => '{field} confirmation: Entries do not match',
    'dimensions' => "{field} dimensions must be exactly [{dimensions}]{unit}",
    'email' => 'Valid {field} is required.',
    'fileType' => 'File must be one of {file_types}{more_info}',
    'image' => '{field} must be a valid image{more_info}',
    'length' => "{field} must be exactly {length} characters long",
    'max' => "{field} must not be more than {max}",
    'maxHeight' => "{field} height must not be more than {max_height}{unit}{more_info}",
    'maxLength' => "{field} must not be more than {max_length} characters long",
    'maxWidth' => "{field} width must not be more than {max_width}{unit}{more_info}",
    'min' => "{field} must not be less than {min}",
    'minHeight' => "{field} height must not be less than {min_height}{unit}{more_info}",
    'minLength' => "{field} must not be less than {min_length} characters long",
    'minWidth' => "{field} width must not be less than {min_width}{unit}{more_info}",
    'password' => '{field} must have at least {more_info}',
    'required' => '{field} is required',
    'size' => "{field} must be equal to {size}",
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


  protected function getValidatorParams($key)
  {
    if(is_null($validator = $this->validator[ $key ])){
      return null;
    }

    if(is_callable($validator)){
      $validator = call_user_func($validator, $this->post);
    }

    $rules = explode('|', $validator['check']);

    foreach ($rules as &$rule){
      if(stristr($rule, ':')){
        $rule = explode(':', $rule);
      }
    }

    $field = isset($validator['as']) ? $validator['as'] : null;
//    pr(['usr' => __FUNCTION__, '$key' => $key, '$validator' => $validator, '$rules' => $rules, '$field' => $field]);

    return [$rules, $field];
  }


  protected function composeReport($failed_rule)
  {
    $report = static::$messages[ $failed_rule ];

    if(array_key_exists('all', $this->replaces)){
      $report = str_replace('{field}', $this->replaces['field'], $this->replaces['all']);
    }
    else {
      foreach ($this->replaces as $find => $replace){
        $report = str_replace('{'.$find.'}', $replace, $report);
      }
    }
    pr(['usr' => __FUNCTION__, '$failed_rule' => $failed_rule, 'replaces' => $this->replaces]);

    $this->replaces = [];

    $regex = "/{[ ]*([^ {}]+)+[^{}]*}/";

//    return preg_replace($regex, '', $report);
    // ToDo: use this line for debug only, the previous line for production
    return $report;
  }


  protected function applyRule($rule, $key, $value, $field = ''){
    if( ! $field){
      $field = ucfirst($key);
    }

    list($function, $arguments) = is_array($rule) ? $rule : [$rule, null];
    pr(['usr' => __FUNCTION__, '$function' => $function, '$arguments' => $arguments]);

    if ($function !== 'password' && ! is_array($value)) {
      $value = static::clean($value);
    }

    $value = call_user_func([static::class, $function], $value, $arguments);
    pr(['usr' => __FUNCTION__, '$rule' => $rule, '$key' => $key, '$value' => $value]);

    if($error = is_null($value)) {
      $this->replaces['field'] = $field;

      $report = static::composeReport($function);

//      $this->errors[] = ['field' => $key, 'errors' => $report];
      if( ! in_array($report, $this->errors[$key])){
        $this->errors[$key][] = $report;
      }
    }

    return ! $error;
  }


  /**
   * @param array $post       The post data to validate
   * @param array $validator  The rules to validate against
   * @throws InvalidArgumentException
   * @return array
   */
  public function run($post, $validator){
    $this->post = $post;
    $this->validator = $validator;

    $checkedFields = [];

    foreach ($this->post as $key => $value) {
      $still_valid = true;

      if (array_key_exists($key, $this->validator)) {

        if( is_null($validator_params = $this->getValidatorParams($key))){
          $checkedFields[] = $key;
          continue;
        }

        list($rules, $field) = $validator_params;

        foreach ($rules as $rule){
          $rule_name = is_array($rule) ? $rule[0] : $rule;

          if ( ! array_key_exists($rule_name, static::$rules)) {
            throw new InvalidArgumentException("Validation rule '{$rule_name}' does not exist");
          }

          $checkedFields[] = $key;

          $valid = $this->applyRule($rule, $key, $value, $field);
          pr(['usr' => __FUNCTION__, '$key' => $key, '$rule' => $rule, '$valid' => $valid]);

          // Once there is an error, $still_valid = false
          if( ! $valid && $still_valid){
            $still_valid = false;
          }
        }
      }
    }

    if ($omittedFields = array_diff( array_keys($validator), $checkedFields)) {
      foreach ($omittedFields as $key) {
        $this->applyRule(self::REQUIRED, $key, '');
      }
    }

    return [ array_unique($checkedFields), $this->errors ];
  }



  /*==================================================================================
   |   V A L I D A T I O N   F U N C T I O N S   H E L P E R S
   *------------------------------------------------------------------------------ */

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

    foreach ($chars as $i => $char){
      if(( ! $name = array_search($char, $characters)) && in_array($char, $doubles)){

        foreach ($paired_characters as $key => $values){
          if(in_array($char, $values)){
            $name = $key;
            break;
          }
        }
      }

      if($name && ! in_array($name, $chars_names)){
        $chars_names[] = $name;
      }
    }

    if( ! empty($chars_names)){
      $chars_count = count($chars_names);

      $chars_names[ $chars_count - 1 ] = 'or ' . $chars_names[ $chars_count - 1 ];

      $chars_names = implode(', ', $chars_names);
    }

    return $chars_names;
  }


  protected function fileObject(array $file, array $options = [])
  {
    return new UploadedFile( $file, $options );
  }


  protected function getSizeInfo($value, $size)
  {
    $size = Arr::stripEmpty( explode(',', $size) );

    if(is_array($value) && array_key_exists('tmp_name', $value)){
      $spl_file = $this->fileObject( $value );

      $actual_size = $spl_file->getSize();

      $allowed_size = $spl_file->fileSizeToBytes($size);

      if($size[1] === 'K'){ $size[1] = 'k'; }

      $allowed_size_formatted = implode('', $size) . 'B';

    }
    else if(is_string($value) || is_numeric($value)){
      $actual_size = trim($value);

      $allowed_size = $allowed_size_formatted = trim($size[0]);

    }
    else {
      throw new InvalidArgumentException('$value', __FUNCTION__);
    }

    return [
      $actual_size, $allowed_size, $allowed_size_formatted
    ];
  }


  /*==================================================================================
   |   V A L I D A T I O N   F U N C T I O N S  -  Arranged in alphabetical order
   *------------------------------------------------------------------------------ */

  public function address($string)
  {
    $allowedChars = ['#', '.', ',', '_', ' ', '-', '@', '(', ')'];

    return $this->alphanumeric_chars(trim($string), $allowedChars);
  }


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
      $chars_names = static::get_charsLiterals($chars);

      if($chars){
        $this->replaces = [
          'more_info' => ", plus (optional) $chars_names"
        ];
      }
    }

    return ($valid) ? [$string] : null;
  }


  public function alphanumeric_space($string)
  {
    $allowedChars = [' '];

    return $this->alphanumeric_chars($string, $allowedChars);
  }


  public function confirmed($value, $confirm_value)
  {
    if(empty($value) or empty($confirm_value) or $value !== $confirm_value){
      return null;
    }

    return [$value];
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


  public function dimensions(array $image, $length, string $operator = null)
  {
    $image_dimensions = $this->getImageDimensions( $image );

    $length = $operator ? (int) $length : $length;

    if( empty($image_dimensions['width']) || empty($image_dimensions['height'])){

      $this->replaces['all'] = static::$messages['image'];

      return null;
    }

    elseif($operator === 'maxHeight' && $image_dimensions['height'] > $length) {
      $this->replaces['max_height'] = $length;
    }

    elseif($operator === 'maxWidth' && $image_dimensions['width'] > $length){
      $this->replaces['max_width'] = $length;
    }

    elseif($operator === 'minHeight' && $image_dimensions['height'] < $length) {
      $this->replaces['min_height'] = $length;
    }

    elseif($operator === 'minWidth' && $image_dimensions['width'] < $length){
      $this->replaces['min_width'] = $length;
    }

    elseif( ! $operator){
      [$width, $height] = Arr::stripEmpty( explode('x', $length) );

      if($image_dimensions['width'] !== $width && $image_dimensions['height'] !== $height){
        $this->replaces['dimensions'] = $length;
      }
    }

    // ToDo: add support for custom, developer-provided units
    if( ! empty($this->replaces)){
      $this->replaces['unit'] = 'px';
    }

    return ( ! $this->replaces) ? [$length] : null;
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

    return [$string];
  }


  public function fileType(array $file, string $extensions)
  {
    $extensions = Arr::stripEmpty( explode(',', $extensions) );

    $options = [
      'extensions' => $extensions
    ];
    pr(['usr' => __FUNCTION__, '$file' => $file, '$options' => $options]);

    $spl_file = $this->fileObject( $file, $options );

    if( ! $spl_file->isFile()){
      $this->replaces['more_info'] = '. File is empty';
    }
    elseif ( ! $spl_file->hasValidMimeType($extensions)){
      $this->replaces['more_info'] = '. Invalid file type';
    }

    if($this->replaces){
      $this->replaces['file_types'] = implode(',', Str::addSingleQuotes($extensions));
      pr(['usr' => __FUNCTION__, 'valid' => empty($this->replaces), '$replaces' => $this->replaces]);

      return null;
    }

    return [$file];
  }


  protected function getImageDimensions(array $file, $operator = null)
  {
    if($operator && ! method_exists($this, $operator)){
      throw new InvalidArgumentException($operator, __FUNCTION__);
    }

    [$width, $height] = ($image = $this->image( $file, true ))
//      ? [ imagesx($image[0]), imagesy($image[0]) ]
      ? [ 205, 500 ]
      : [null, null];

    pr(['usr' => __FUNCTION__, '$operator' => $operator, '$height' => $height, '$width' => $width, '$image' => $image[0], '$file' => $file]);

    return compact('width', 'height');
  }


  protected function getImageBuilder(string $extension)
  {
    if(in_array($extension, ['jpg', 'jpeg'])){
      $extension = 'jpeg';
    }

    // ToDO: see also 'imagecreatefromstring()'
    if(function_exists($function = "imagecreatefrom{$extension}")){
      return $function;
    }

    return null;
  }


  /**
   * @param array $file         The uploaded image file
   * @param bool  $internal_op  If true (internal operation), returns the [$image, $spl_file] for further use
   * @return array|null
   */
  public function image(array $file, bool $internal_op = null)
  {
    $spl_file = $this->fileObject( $file );
    pr(['usr' => __FUNCTION__, '$file' => $file, '$spl_file' => $spl_file, '$internal_op' => $internal_op]);

    if( ! $image = $spl_file->fileCategory() === 'image'){
      return null;
    }

    if($builder = $this->getImageBuilder( $spl_file->extension() )){
      if( ! $image = call_user_func($builder, $spl_file->tmpName()) ){
        return null;
      }
      elseif( ! $internal_op && ! empty($image)) {
         imagedestroy($image);
      }
    }

    return $internal_op ? [$image] : [$spl_file];
  }


  public function length($string, int $length, string $operator = null)
  {
    if($operator && ! method_exists($this, $operator)){
      throw new InvalidArgumentException($operator, __FUNCTION__);
    }

    $string = trim($string);

    if($operator === 'maxLength' && strlen($string) > $length) {
      $this->replaces['max_length'] = $length;
    }
    elseif($operator === 'minLength' && strlen($string) < $length){
      $this->replaces['min_length'] = $length;
    }
    elseif( ! $operator && strlen($string) !== $length){
      $this->replaces['length'] = $length;
    }

    return ( ! $this->replaces) ? [$string] : null;
  }


  public function max($value, $max)
  {
    [ $actual_size, $allowed_size, $allowed_size_formatted ] = $this->getSizeInfo($value, $max);

    if($actual_size > $allowed_size){
      $this->replaces['max'] = $allowed_size_formatted;
    }

    return ( ! $this->replaces) ? [$actual_size] : null;
  }


  /*public function maxHeight($image, $max_height)
  {
    $image_dimensions = $this->getImageDimensions( $image );
    pr(['usr' => __FUNCTION__, '$image' => $image, '$image_dimensions' => $image_dimensions]);

    if($image_dimensions['height'] > $max_height) {
      $this->replaces['max_height'] = $max_height;

      return null;
    }

    return [$image];
  }*/
  public function maxHeight(array $image, $max_height)
  {
    return $this->dimensions($image, $max_height, __FUNCTION__);
  }


  public function maxLength($string, $max_length)
  {
    return $this->length($string, $max_length, __FUNCTION__);
  }


  public function maxWidth(array $image, $max_width)
  {
    return $this->dimensions($image, $max_width, __FUNCTION__);
  }


  public function min($value, $min)
  {
    [ $actual_size, $allowed_size, $allowed_size_formatted ] = $this->getSizeInfo($value, $min);

    if($actual_size < $allowed_size) {
      $this->replaces['min'] = $allowed_size_formatted;
    }


    return ( ! $this->replaces) ? [$actual_size] : null;
  }


  public function minHeight(array $image, $min_height)
  {
    return $this->dimensions($image, $min_height, __FUNCTION__);
  }


  public function minLength($string, $min_length)
  {
    return $this->length($string, $min_length, __FUNCTION__);
  }


  public function minWidth(array $image, $min_width)
  {
    return $this->dimensions($image, $min_width, __FUNCTION__);
  }


  public function password($password, $arguments = [])
  {
    $replaces = $this->contains($password, $arguments);

    if(empty($replaces)) {
      return [$password];
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

      $this->replaces['more_info'] = implode(', ', $replaces);

      return null;
    }
  }


  public function required($value)
  {
    $value = trim( strval($value));

    return ($value !== '') ? [$value] : null;
  }


  public function size($value, $size)
  {
    [ $actual_size, $allowed_size, $allowed_size_formatted ] = $this->getSizeInfo($value, $size);

    if($actual_size !== $allowed_size){
      $this->replaces['size'] = $allowed_size_formatted;
    }

    return ( ! $this->replaces) ? [$actual_size] : null;
  }


  public function text($string)
  {
    $value = trim( strval($string));

    // ToDo: any further checks ???
    return [$value];
  }

}
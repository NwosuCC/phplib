<?php

namespace Orcses\PhpLib;


use Orcses\PhpLib\Exceptions\InvalidArgumentException;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Utility\Str;

class Result
{
  protected static $reports = [];

  protected $code, $message, $http_status_code, $info;

  protected $key, $type, $result;

  protected $response;


  public function __construct(array $result = [], array $info = [], array $attributes = []){
    $this->result = $result;

    $this->info = $info;

    $this->key = $attributes['key'];

    $this->type = $attributes['type'];
  }


  public static function success(string $key, int $index, array $replaces = null, array $info = null)
  {
    $attributes = ['key' => $key, 'type' => 'success'];

    if( ! static::$reports){
      static::$reports = Response::getReportMessages();
    }

    if(strlen($index) === 1){
      $index = "0{$index}";
    }

    $code = static::$reports[ $key ]['code'] . '00';
//    pr(['usr' => __FUNCTION__, '$index' => $index, '$code' => $code, 'error' => static::$reports[ $key ]['error']]);

    $message_array = static::$reports[ $key ]['success'][ $index ];

    [$message, $http_code] = Arr::pad( $message_array, 2, 200 );
    pr(['usr' => __FUNCTION__, '$message' => $message, '$http_code' => $http_code, '$replaces' => $replaces, '$key' => $key]);

    if( ! empty($replaces) and is_array($replaces)){

      $message = Str::replaces($message, $replaces);
    }

    $regex = "/{[ ]*([^ {}]+)+[^{}]*}/";

    $message = preg_replace($regex, '', $message);

    return new static([$code, $message, $http_code], $info ?: [], $attributes);
  }


  public static function error(string $key, int $index, array $replaces = null, array $info = null)
  {
    $attributes = ['key' => $key, 'type' => 'error'];

    if( ! static::$reports){
      static::$reports = Response::getReportMessages();
    }

    if(strlen($index) === 1){
      $index = "0{$index}";
    }

    $code = static::$reports[ $key ]['code'] . $index;

//    pr(['usr' => __FUNCTION__, '$index' => $index, '$code' => $code, 'error' => static::$reports[ $key ]['error']]);

    $message_array = static::$reports[ $key ]['error'][ $index ];

    $status_code = static::$reports[ $key ]['error']['http_error_code'] ?? 400;

    [$message, $http_code] = Arr::pad( $message_array, 2, $status_code );
//    pr(['usr' => __FUNCTION__, '$message' => $message, '$http_code' => $http_code, '$replaces' => $replaces]);

    if( ! empty($replaces) and is_array($replaces)){

      $message = Str::replaces($message, $replaces);
    }

    $regex = "/{[ ]*([^ {}]+)+[^{}]*}/";

    $message = preg_replace($regex, '', $message);

    return new static([$code, $message, $http_code], $info ?: [], $attributes);
  }


  public static function prepare(array $result)
  {
    if( ! static::$reports){
      static::$reports = Response::getReportMessages();
    }

    $notice = [];

    list($function, $error_number, $info) = static::validateResult( ...$result );

    if(is_array($error_number) and count($error_number) === 2){
      list($error_number, $replaces) = $error_number;
    }

    if(strlen($error_number) > 1){

      $indices = str_split($error_number);

      if((int) $indices[0] === 0){
        list($error_number, $message_index) = array_splice($indices, 0, 2);
      }
    }

    if( ! $error_number){
      $result = static::$reports[$function]['success'];

      if( ! empty($message_index)){
        // replaces - success
        $result[1] = $result[1][$message_index];
      }

      if(empty($result[2])){ // Default Http Success Code
        $result[2] = 200;
      }
    }
    else{
      $result = static::$reports[$function]['error'][$error_number];

      if(empty($result[2])){ // Http Error Code
        $result[2] = static::$reports[$function]['error']['http_error_code'] ?? 400;
      }
    }

    /*if(isset($indices)){
      $notice = static::$reports[$function]['error'];
      $index_results = [];

      foreach($indices as $index){
        $index_results[] = $notice[$index];
      }
      $notice = $index_results;
    }*/

    // Apply the $replaces on the stubs
    if( ! empty($replaces) and is_array($replaces)){

      $result[1] = Str::replaces($result[1], $replaces);
    }
    
//    if( ! empty($notice)){
//      $result[1] = [$result[1], $notice];
//    }

    $regex = "/{[ ]*([^ {}]+)+[^{}]*}/";
    $result[1] = preg_replace($regex, '', $result[1]);

    return new static($result, $info);
  }


  protected static function validateResult( $report_key, $code, array $info )
  {
    if( ! report()->has( $report_key )){

      $report = "Report Key '{$report_key}' does not exist";

      throw new InvalidArgumentException( $report );
    }

    /*if(count($result) < 2){
      throw new \OutOfRangeException(
        "Result::prepare() expects argument \$result to be an array of at least two (2) items"
      );
    }*/

    if( ! isset($result[2])){
      $result[2] = [];
    }

    return $result;
  }


  public function prepareAndSend($data)
  {
    return $this->prepare($data)->composeResponse();
  }


  public function getKey()
  {
    return $this->key;
  }


  public function getType()
  {
    return $this->type;
  }


  public function getData(){
    return [$this->result, $this->info];
  }


  public function getResponseData(){
    if($this->result && ! $this->response){
      $this->composeResponse();
    }

    return [$this->http_status_code, $this->response];
  }


  public function composeResponse(){
    if( ! $this->result){
      // throw Exception No result
      return null;
    }

    list($code, $message, $this->http_status_code) = $this->result;

    $this->response = [
      'status' => $this->status(),
      'code' => $code,
      'message' => $message,
      'info' => $this->info,
    ];

    return $this;
  }


  protected function status(){
    return $this->isSuccessful() ? 'Success' : 'Error';
  }


  protected function isSuccessful()
  {
    return strtolower( $this->getType() ) === 'success';
  }

}
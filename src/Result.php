<?php

namespace Orcses\PhpLib;


class Result
{
  protected static $reports = [];

  protected $result, $info;

  protected $http_status_code, $response;


  public function __construct(array $result = [], array $info = []){
    $this->result = $result;

    $this->info = $info;
  }


  public function prepareAndSend($data)
  {
    return $this->prepare($data)->composeResponse();
  }


  public static function prepare(array $result)
  {
    if( ! static::$reports){
      static::$reports = Response::getReportMessages();
    }

    $notice = [];

    if(count($result) < 2){
      throw new \OutOfRangeException(
        "Result::prepare() expects argument \$result to be an array of at least two (2) items"
      );
    }

    if( ! isset($result[2])){
      $result[2] = [];
    }

    list($function, $error_number, $info) = $result;

    if(is_array($error_number) and count($error_number) === 2){
      list($error_number, $replaces) = $error_number;
    }

    if(strlen($error_number) > 1){
      $indices = str_split($error_number);

      list($error_number, $message_index) = array_splice($indices, 0, 2);
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

    if(isset($indices)){
      $notice = static::$reports[$function]['error'];
      $index_results = [];

      foreach($indices as $index){
        $index_results[] = $notice[$index];
      }
      $notice = $index_results;
    }

    // Apply the $replaces on the stubs
    if( ! empty($replaces) and is_array($replaces)){
      foreach ($replaces as $find => $replace){
        $result[1] = str_replace('{'.$find.'}', $replace, $result[1]);
      }
    }
    
    if( ! empty($notice)){
      $result[1] = [$result[1], $notice];
    }

    $regex = "/{[ ]*([^ {}]+)+[^{}]*}/";
    $result[1] = preg_replace($regex, '', $result[1]);

    return new static($result, $info);
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


  protected function isSuccessful(){
    $code = $this->result[0];

    $success_codes = ['0', '00', '000'];

    return in_array($code, $success_codes);
  }

}
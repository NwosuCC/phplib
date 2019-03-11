<?php

namespace Orcses\PhpLib;


Abstract class Result {
  private static $result, $info;

  private static $reports;

  public static function prepareAndSend($data) {
    static::prepare($data);

    static::dispatch();
  }


  public static function sendPrepared(array $result, array $info) {
    self::$result = $result;
    self::$info = $info;

    static::dispatch();
  }


  public static function prepare($result){
    global $_REPORTS;
    static::$reports = $_REPORTS;
    $notice = [];

    if(!is_array($result) or count($result) < 2){
      return null;
    }

    if(!isset($result[2])){ $result[2] = []; }


    list($function, $error_number, $info) = $result;

    if(is_array($error_number)){
      list($error_number, $replaces) = $error_number;
    }

    if(strlen($error_number) > 1){
      $indices = str_split($error_number);
      list($error_number, $message_index) = array_splice($indices, 0, 2);
//            array_splice($indices, 0, 2);
    }

    if(!$error_number){
      $result = static::$reports[$function]['success'];

      if(!empty($message_index)){
        $result[1] = $result[1][$message_index];
      }

      if(empty($result[2])){ // Http Success Code
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
    if(!empty($replaces) and is_array($replaces)){
      foreach ($replaces as $find => $replace){
        $result[1] = str_replace('{'.$find.'}', $replace, $result[1]);
      }
    }
    if(!empty($notice)){
      $result[1] = [$result[1], $notice];
    }

    // self::$result : [$internal_code, $message, $http_status_code]
    self::$result = $result;
    self::$info = $info;

    return [self::$result, self::$info];
  }


  protected static function dispatch(bool $send = true){
    list($code, $message, $http_status_code) = static::$result;

    $response = [
      'status' => static::status($code),
      'code' => $code,
      'message' => $message,
      'info' => static::$info,
    ];

    if($send){
      Response::send($http_status_code, $response);
    }

    return [$http_status_code, $response];
  }


  protected static function status($code){
    return (static::successful($code)) ? 'Success' : 'Error';
  }


  public static function successful($result){
    $code = (is_array($result)) ? $result[0] : $result;

    $success_codes = ['0', '00', '000'];

    return in_array($code, $success_codes);
  }

}
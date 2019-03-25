<?php

namespace Orcses\PhpLib;


class Response
{
  private $CORS_allowed_Urls;

  private $http_status_code, $http_status_message, $body;


  public function __construct(int $http_code, array $data = []) {

    $this->setHttpCode($http_code)->setBody($data);

  }


  public static function package(Result $result){
    [$http_code, $data] = $result->getResponseData();

    return new static($http_code, $data);
  }


  public function setHttpCode($http_status_code){
    $http_status_message = '';

    if(is_array($http_status_code)){
      [$http_status_code, $http_status_message] = $http_status_code;
    }

    $this->http_status_code = $http_status_code;

    $this->http_status_message = $http_status_message;

    return $this;
  }


  public function setBody(array $data)
  {
    $this->body = $data;

    return $this;
  }


  public function set_CORS_allowed_Urls()
  {
    if( is_null($this->CORS_allowed_Urls)){

      $envUrls = explode(',', app()->config('http.cors.allow'));

      $allowed_Urls = array_map('trim', $envUrls);

      $this->CORS_allowed_Urls = implode(',', $allowed_Urls);
    }
  }


  public function send() {
    $this->sendHeaders();

    die(json_encode($this->body));
  }


  private function sendHeaders() {
    $http_status_code = $this->http_status_code ?: 200;

    $http_status_message = $this->http_status_message ?: '';

    $php_SApi_name = substr(php_sapi_name(), 0, 3);

    $protocol = in_array($php_SApi_name, ['cgi', 'fpm'])
      ? 'Status:'
      : $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0';

    if(static::$CORS_allowed_Urls){
      header('Access-Control-Allow-Origin: ' . static::$CORS_allowed_Urls);
    }

    header('Access-Control-Allow-Headers: Content-Type');

    header('Content-Type: application/json', true);

    header($protocol .' '. $http_status_code .' '. $http_status_message);
  }

}
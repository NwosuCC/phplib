<?php

namespace Orcses\PhpLib;


class Response
{
  private $CORS_allowed_Urls;

  private $http_status_code, $http_status_message, $body;


  public function __construct(int $http_code, array $data = [])
  {
    $this->setHttpCode($http_code)->setBody($data);

    $this->set_CORS_allowed_Urls();
  }


  public static function get(array $result)
  {
    return self::package( Result::prepare($result) );
  }


  public static function package(Result $result)
  {
    [$http_code, $data] = $result->getResponseData();

    return new static($http_code, $data);
  }


  public function setHttpCode($http_status_code)
  {
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


  public function send()
  {
    $this->sendHeaders();

    die(json_encode($this->body));
  }

  /*public function send()
  {
    $http = new \HttpResponse();

    $headers = [
      'Access-Control-Allow-Origin' => $this->CORS_allowed_Urls ?? '',
      'Access-Control-Allow-Headers' => 'Content-Type',
//      'Content-Type' => 'application/json',
    ];

    foreach($headers as $name => $value){
      $http->setHeader($name, $value);
    }

    $http->setContentType('application/json');

//    $http->setData(json_encode($this->body));
    $http->status( $this->http_status_code );

    $http->setData( $this->body );

  }*/


  private function sendHeaders()
  {
    $http_status_code = $this->http_status_code ?: 200;

    $http_status_message = $this->http_status_message ?: '';

    $php_SApi_name = substr(php_sapi_name(), 0, 3);

    $protocol = in_array($php_SApi_name, ['cgi', 'fpm'])
      ? 'Status:'
      : $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0';

    if($this->CORS_allowed_Urls){
      header('Access-Control-Allow-Origin: ' . $this->CORS_allowed_Urls);
    }

    header('Access-Control-Allow-Headers: Content-Type');

    header('Content-Type: application/json', true);

    header($protocol .' '. $http_status_code .' '. $http_status_message);
  }

}
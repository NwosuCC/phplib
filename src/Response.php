<?php

namespace Orcses\PhpLib;


class Response
{
  private static $CORS_allowed_Urls = '';


  public static function set_CORS_allowed_Urls(array $allowed_Urls) {
    $allowed_Urls = array_map(function ($url){ return trim($url); }, $allowed_Urls);

    static::$CORS_allowed_Urls = implode(',', $allowed_Urls);
  }


  public static function send($http_code, $data) {
    static::sendHeaders($http_code);

    die(json_encode($data));
  }


  private static function sendHeaders(int $code, string $message = '') {
    $http_status_code = $code ?: 200;
    $http_status_message = $message ?: '';

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
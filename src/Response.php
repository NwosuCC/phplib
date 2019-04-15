<?php

namespace Orcses\PhpLib;


use Exception;
use Orcses\PhpLib\Exceptions\FileNotFoundException;

class Response
{
  /** @var static */
  protected static $instance;

  protected static $reports = [];

  protected static $success_codes = [];

  protected $CORS_allowed_Urls;

  protected $body, $packaged = false;

  protected $http_status_code, $response_message = '';


  public function __construct(int $http_code = 200, array $data = [])
  {
    $this->setHttpCode($http_code)->setBody($data);

    $this->set_CORS_allowed_Urls();

    if( ! static::$instance){
      // ToDo: make a list of valid Status Codes
      static::$success_codes = range(200, 320);

      static::$instance = $this;
    }
  }


  public static function instance(int $http_code)
  {
    // ToDo: make a list of valid Status Codes
    return static::$instance->setHttpCode($http_code);
  }


  /**
   * Packages a raw response object for dispatch
   * @param array $data
   * @return  static
   */
  public function json(array $data)
  {
    $code = in_array($this->http_status_code , static::$success_codes) ? '01' : 1;

    $replaces = ['message' => $this->response_message];

    return $this->get( [report()::APP, [$code, $replaces], $data] );
  }


  public function message(string $message)
  {
    $this->response_message = $message;

    return $this;
  }


  public static function get(array $result)
  {
    return self::package( Result::prepare($result) );
  }


  public static function package(Result $result)
  {
    [$http_code, $data] = $result->getResponseData();

    $response = new static($http_code, $data);

    $response->packaged = true;

    return $response;
  }


  public function setHttpCode($http_status_code)
  {
    $this->http_status_code = (int) $http_status_code;

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
    if( ! ($response = $this)->packaged ){

      [$status_code, $message] = [$response->http_status_code, $response->response_message];

      $response = $response->json( $response->body );

      $response->setHttpCode( $status_code );
      $response->message( $message );
    }

    $response->sendHeaders();

    die(json_encode($response->body));
  }


  // ToDo: Use this standard HttpResponse class
  public function send_New()
  {
    /*$http = new \HttpResponse();

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

    $http->setData( $this->body );*/
  }


  private function sendHeaders()
  {
    $http_status_code = $this->http_status_code ?: 200;

    $php_SApi_name = substr(php_sapi_name(), 0, 3);

    $protocol = in_array($php_SApi_name, ['cgi', 'fpm'])
      ? 'Status:'
      : $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0';

    if($this->CORS_allowed_Urls){
      header('Access-Control-Allow-Origin: ' . $this->CORS_allowed_Urls);
    }

//    header('X-Powered-By: PLiza');
//    header('Server: (c)2017 PLiza Inc.', true);

    // ToDo: use this instead
    // NOTE: 'Server' removal hasn't worked, is this necessary??
    $guarded_headers = [
      'X-Powered-By', 'Server'
    ];

    foreach($guarded_headers as $guarded){
      header_remove( $guarded );
    }

    header('Access-Control-Allow-Headers: Content-Type');

    header('Content-Type: application/json', true);

    header($protocol .' '. $http_status_code);
  }


  public static function getReportMessages()
  {
    return static::$reports;
  }


  /**
   * Load response messages at App start
   */
  public static function loadReportMessages()
  {
    try {
      $file_path = base_dir() . app()->config('response.reports') .'.php';

      if(file_exists($file_path)){
        $app_reports = require ( ''.$file_path.'' );

        $sys_reports = Report::defaults();

        static::$reports = array_merge( $sys_reports, $app_reports);
      }
    }
    catch (Exception $e){
      throw new FileNotFoundException("Reports", $file_path ?? '');
    }
  }


}
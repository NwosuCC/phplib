<?php

namespace Orcses\PhpLib;


class Report
{
  protected static $instance;

  const APP = 'App';
  const PING = 'Ping';
  const ACCESS = 'Access';
  const VALIDATION = 'Validation';

  protected static $report_keys;


  protected function __construct()
  {
  }


  public static function instance()
  {
    if( ! static::$instance){
      static::$instance = new static();
    }

    return static::$instance;
  }


  public static function defaults()
  {
    return self::$REPORTS;
  }


  public static function has(string $key)
  {
    if( ! static::$report_keys){
      static::$report_keys = array_keys( Response::getReportMessages() );
    }

    return in_array( $key, static::$report_keys);
  }


  /**
   * Lets the developer set the Welcome message
   * @param string $message
   */
  public static function welcome(string $message)
  {
    self::$REPORTS[ self::ACCESS ]['success'][1] = $message;
  }


  protected static $REPORTS = [

    self::PING => [
      'code' => '00',
      'success' => [
        '000', 'Server alive', 204 // No Response Content
      ],
      'error' => [
        '1' => ['001', 'reCaptcha (o)', 401],
      ],
    ],

    self::APP => [
      'code' => '01',
      'success' => [
        '000', ''
      ],
      'error' => [
        '1' => ['011', '{message}'],
        '2' => ['012', 'Unknown Request', 404],
        '3' => ['013', 'Error occurred. Support is on it right away!'],
      ],
    ],

    self::ACCESS => [
      'code' => '03',
      'success' => [
        '000', 'Welcome, {name}'
      ],
      'error' => [
        '1' => ['031', 'Invalid User Credentials. Please, try again', 401],
        '2' => ['032', 'You are not authorized to perform this operation', 403],
        '3' => ['033', 'Please, log in to continue', 401],
        '4' => ['034', '', 204], // ToDo: remove this logout payload, returning No Content (204)
        '5' => ['035', 'Please, create a new Authorization Key now to secure Super-Admin privileges!', 200],
        '6' => ['036', 'Please, try again after {minutes} minutes or contact Support!', 403],
        '7' => ['037', '{token_error}', 401],
      ],
    ],

    self::VALIDATION => [
      'code' => '09',
      'success' => [
        '000', ''
      ],
      'error' => [
        '1' => ['091', 'There were errors in the input' ],
      ],
      'http_error_code' => 400
    ],



  ];


}
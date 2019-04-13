<?php

namespace Orcses\PhpLib;

use Exception;


class Logger {
  // Specify log directory and file(s) in config.php
  protected static $log_dir;

  private static $files = [
    'error' => 'error-log.txt',
    'sql' => 'sql-log.txt',
  ];

  public static function log($type, $details)
  {
    if( ! static::$log_dir){
      static::$log_dir = base_dir() . app()->config('files.log.dir');
    }

    if( empty(static::$files[$type]) || empty($details) ){

      $error_message = empty(static::$files[$type])
        ? "Type [$type] is not supported"
        : 'Parameter $details must not be empty';

      throw new Exception(__METHOD__ . '() ' . $error_message);
    }

    if($report = static::composeReport($type, $details)){

      $logfile = static::getLogFilePath($type);

      $fileHandle = fopen($logfile,'a');

      fwrite($fileHandle, $report);
      fclose($fileHandle);
    }

    return !empty($report);
  }


  protected static function composeReport($type, $details)
  {
    $report = null;

    list($code, $message) = is_array($details) ? $details : ['--', $details];

    $message = trim($message);

    $remote_domain = $_SERVER['REMOTE_ADDR'] . ':' . $_SERVER['REMOTE_PORT'];

    $request_time = $_SERVER['REQUEST_TIME'];


    if($type == 'error'){
      $request_info = " from {$remote_domain} at " . date('Y-m-d H:i:s', $request_time);

      $report = " Error [{$code}]: {$message} == " . $request_info . PHP_EOL;
    }
    elseif($type === 'sql'){
      $report = " QUERY [affected_rows: {$code}]: {$message}" . PHP_EOL;
    }

    return date('Y-m-d H:i:s', time()) . $report;
  }


  protected static function getLogFilePath($type)
  {
    return static::$log_dir . DIRECTORY_SEPARATOR . static::$files[$type];
  }

}
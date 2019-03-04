<?php

namespace Orcses\PhpLib;

use Exception;


class Logger {
  // Specify log directory and file(s) in config.php
  private static $log_dir;

  private static $files = [
    'error' => 'error-log.txt',
  ];

  public static function log($type, $details){
    self::check_parameters();

    if(empty(static::$files[$type]) or empty($details)){
      $error_message = empty(static::$files[$type])
        ? "Type [$type] is not supported"
        : 'Parameter $details must not be empty';

      throw new Exception('Logger::log() ' . $error_message);
    }

    if($report = static::composeReport($type, $details)){
      $logfile = static::getLogFile($type);

      $fileHandle = fopen($logfile,'a');

      fwrite($fileHandle, $report);
      fclose($fileHandle);
    }

    return !empty($report);
  }

  private static function check_parameters() {
    if(!static::$log_dir){
      requires([
        'LOG_DIR'
      ]);

      static::$log_dir = LOG_DIR;
    }
  }

  private static function composeReport($type, $details){
    $report = null;

    if($type == 'error'){
      list($code, $message) = is_array($details) ? $details : ['--', $details];
      $message = trim($message);

      $report = date('Y-m-d H:i:s', time()) . " Error [{$code}]: {$message}" . PHP_EOL;
    }

    return $report;
  }

  private static function getLogFile($type){
    return static::$log_dir . DIRECTORY_SEPARATOR . static::$files[$type];
  }

}
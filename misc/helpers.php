<?php

use Orcses\PhpLib\Logger;
use Orcses\PhpLib\Result;


if (! function_exists('requires')) {
  /**
   * Ensures all required constants are defined
   *
   * @param  array  $required_constants
   * @param  bool  $should_throw If true, Exception will be thrown on error
   * @throws Exception
   * @return string
   */
  function requires($required_constants, bool $should_throw = true)
  {
    $missing = array_filter($required_constants, function ($param){
      return !defined($param);
    });

    $error = $missing ? "Undefined Logger parameters: " . implode(', ', $missing) : '';

    if($error && $should_throw){
      throw new Exception($error);
    }

    return $error;
  }
}


if (! function_exists('arr_get')) {
  /**
   * Return the array value specified by the key.
   * Supports dot notation keys
   *
   * @param  mixed  $key
   * @param  array  $array
   * @return mixed
   */
  function arr_get($key, array $array)
  {
    $segments = is_array($key) ? $key : explode('.', $key);

    foreach ($segments as $segment) {
      if (!array_key_exists($segment, $array)) {
        break;
      }

      $array = $array[$segment];
    }

    return value($array);
  }
}

if (! function_exists('pr')) {
  /**
   * Dump the data using the specified conditions
   *
   * @param  mixed  $data
   * @param  bool   $json
   * @param  bool   $html
   * @return void
   */
  function pr($data, $json = true, $html = false)
  {
    $newlines = ($html === true) ? '<br><br>' : "\n\n";

    switch (true) {
      case $json : {
        echo json_encode($data); break;
      }
      case is_array($data) : {
        print_r($data); break;
      }
      default : { var_dump($data); } break;
    }

    echo $newlines;
  }
}

if (! function_exists('dd')) {
  /**
   * Call pr() and die() the script
   *
   * @param  mixed  $data
   */
  function dd(...$data)
  {
    foreach ($data as $v) {
      pr($v, true);
    }

    exit();
  }
}

if (! function_exists('safe_call')) {
  /**
   * Run a script in try{} block
   *
   * @param  array  $block
   * @param  array  $callback
   * @return mixed
   */
  function safe_call(array $block, array $callback = [])
  {
    // Sample: in Token::verifyJWT()
    /*$decoded = safe_mode([
      [JWT::class, 'decode'], [$token, $key, static::$algorithm]
    ]);
    if(!$decoded) {
      return false;
    }*/

    try {
      $block_function = $block['function'] ?? $block[0] ?? null;
      $block_params = $block['parameters'] ?? $block[1] ?? [];

      if(is_callable($block_function)){
        return call_user_func_array($block_function, $block_params);
      }
    }
    catch (Exception $e){
      if($callback) {
        $callback_function = $callback['function'] ?? $callback[0] ?? null;
        $callback_params = $callback['parameters'] ?? $callback[1] ?? [];

        if(is_callable($callback_function)){
          return call_user_func_array($callback_function, $callback_params);
        }
      }
    }

    return null;
  }
}

if (! function_exists('value')) {
  /**
   * Return the default value of the given value.
   *
   * @param  mixed  $value
   * @return mixed
   */
  function value($value)
  {
    return $value instanceof Closure ? $value() : $value;
  }
}

if (! function_exists('env')) {
  /**
   * Gets the value of an environment variable.
   *
   * @param  string  $key
   * @param  mixed   $default
   * @return mixed
   */
  function env($key, $default = null)
  {
    $value = getenv($key);

    if ($value === false) {
      return value($default);
    }

    switch (strtolower($value)) {
      case 'true':
      case '(true)':
        return true;
      case 'false':
      case '(false)':
        return false;
      case 'empty':
      case '(empty)':
        return '';
      case 'null':
      case '(null)':
        return null;
    }

    if (($valueLength = strlen($value)) > 1 && $value[0] === '"' && $value[$valueLength - 1] === '"') {
      return substr($value, 1, -1);
    }

    return $value;
  }
}

if (! function_exists('config')) {
  /**
   * Gets the value of a config setting.
   *
   * @param  string  $key
   * @return mixed
   */
  function config($key)
  {
    switch(true){
      case defined('CONFIG_DIR') : {
        $config_dir = constant('CONFIG_DIR'); break;
      }
      case defined('APP_DIR') : {
        $config_dir = constant('APP_DIR') .'/config'; break;
      }
      default : { $config_dir = ''; }
    }

    $config_file = $config_dir . '/app.php';

    try {
      $config = require (''.$config_file.'');

      return arr_get($key, $config);
    }
    catch (Exception $e){}

    return null;
  }
}

if (! function_exists('log_info')) {
  /**
   * Logs info
   *
   * @param  string|array  $message
   * @param  array  $callback
   * @param  bool  $from_report   If true, generates [code, message] from global $_REPORTS
   * @return mixed
   */
  function log_info($message, $callback = [], $from_report = false)
  {
    if(is_array($message)){
      $message = ($from_report) ? (Result::prepare($message)[0] ?? '') : safe_call($message);
    }

    Logger::log('error', $message);

    return safe_call($callback);
  }
}

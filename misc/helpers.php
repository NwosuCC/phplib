<?php

use Orcses\PhpLib\Logger;
use Orcses\PhpLib\Result;


if (! function_exists('app')) {
  /**
   * Return the Application instance
   *
   * @return \Orcses\PhpLib\Application
   */
  function app()
  {
    return \Orcses\PhpLib\Application::instance();
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
  function arr_get(array $array, $key)
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

if (! function_exists('auth')) {
  /**
   * Return the Auth instance
   *
   * @return \Orcses\PhpLib\Access\Auth
   */
  function auth()
  {
    return \Orcses\PhpLib\Access\Auth::auth();
  }
}

if (! function_exists('base_dir')) {
  /**
   * Return the Application base directory
   *
   * @return string
   */
  function base_dir()
  {
    return app()->baseDir();
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
    $config_file = base_dir() . '/config/app.php';

    try {
      $config = require (''.$config_file.'');

      return arr_get($config, $key);
    }
    catch (Exception $e){}

    return null;
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

if (! function_exists('real_dir')) {
  /**
   * Converts the directory path to the local OS format
   * @param string $path
   * @return string
   */
  function real_dir(string $path)
  {
    return str_replace('//', '/', $path);

//    return str_replace('/', DIRECTORY_SEPARATOR, $path);
  }
}

if (! function_exists('real_url')) {
  /**
   * Converts the directory path to the http url format
   * @param string $path
   * @return string
   */
  function real_url(string $path)
  {
    $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);

    return str_replace('//', '/', $path);
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

if (! function_exists('log_error')) {
  /**
   * Logs info
   *
   * @param  string|array  $message
   * @param  array  $callback
   * @param  bool  $from_report   If true, generates [code, message] from global $_REPORTS
   * @return mixed
   */
  function log_error($message, $callback = [], $from_report = false)
  {
    if(is_array($message)){
      $message = ($from_report) ? (Result::prepare($message)[0] ?? '') : safe_call($message);
    }

    Logger::log('error', $message);

    return safe_call($callback);
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
    $allow = [
//      'tmp' => '',
//      'lgc' => ''
      'usr' => '',
    ];
    if(!is_array($data) || ! array_intersect_key($allow, $data)) return;

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

    $error = $missing ? "Undefined constants: " . implode(', ', $missing) : '';

    if($error && $should_throw){
      throw new Exception($error);
    }

    return $error;
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
    // Sample: in JWToken::verifyJWT()
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

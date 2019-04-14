<?php

if (! function_exists('app')) {
  /**
   * Return the Application instance
   * @param string $class_name  If supplied, returns an instance of the class, else, the App instance
   * @return \Orcses\PhpLib\Application
   */
  function app(string $class_name = null)
  {
    $app = \Orcses\PhpLib\Application::instance();

    return $class_name ? $app->make( $class_name ) : $app;
  }
}


if (! function_exists('app_url')) {
  /**
   * Return the Application base url
   *
   * @return string
   */
  function app_url()
  {
    return config('app.url');
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
      if ( ! array_key_exists($segment, $array)) {
        break;
      }

      $array = $array[ $segment ];
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
//    return \Orcses\PhpLib\Access\Auth::auth();
    return app()->make(\Orcses\PhpLib\Access\Auth::class)->auth();
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


if (! function_exists('error')) {
  /**
   * @param  string $report_key
   * @param  int $code
   * @param  array $replaces
   * @return array
   */
  function error(string $report_key, $code, $replaces = [])
  {
    $code = $replaces ? [$code, $replaces] : $code;

    return [$report_key, $code];
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
      $message = ($from_report)
        ? (Orcses\PhpLib\Result::prepare($message)[0] ?? '')
        : safe_call($message);
    }

    \Orcses\PhpLib\Logger::log('error', $message);

    return safe_call( $callback );
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
//      'lgc' => '',
      'usr' => '',
//      'alg' => '',
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


if (! function_exists('real_dir')) {
  /**
   * Converts the directory path to the local OS format
   * @param string $path
   * @return string
   */
  function real_dir(string $path)
  {
    $path = str_replace('//', '/', $path);

    return str_replace('/', DIRECTORY_SEPARATOR, $path);
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


if (! function_exists('report')) {
  /**
   * Returns an instance of the Report class
   * @return \Net\Report | \Orcses\PhpLib\Report
   */
  function report()
  {
    if(class_exists(\Net\Report::class)){
      return \Net\Report::instance();
    }

    return \Orcses\PhpLib\Report::instance();
  }
}


if (! function_exists('response')) {
  /**
   * Returns an instance of the Response class
   * @param int $http_code
   * @return \Orcses\PhpLib\Response
   */
  function response(int $http_code = 200)
  {
    return \Orcses\PhpLib\Response::instance($http_code);
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


if (! function_exists('route')) {
  /**
   * Returns the uri for the named route
   *
   * @param  string $name
   * @return null|\Orcses\PhpLib\Routing\Router $route
   */
  function route(string $name)
  {
    return \Orcses\PhpLib\Routing\Router::findByName( $name );
  }
}


if (! function_exists('safe_call')) {
  /**
   * Run a script in try{} block
   *
   * @param  array  $block
   * @param  array  $error_callback
   * @return mixed
   */
  function safe_call(array $block, array $error_callback = [])
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
      if($error_callback) {
        $callback_function = $error_callback['function'] ?? $error_callback[0] ?? null;
        $callback_params = $error_callback['parameters'] ?? $error_callback[1] ?? [];

        if(is_callable($callback_function)){
          return call_user_func_array($callback_function, $callback_params);
        }
      }
    }

    return null;
  }
}


if (! function_exists('success')) {
  /**
   * @param  string $report_key
   * @param  array $info
   * @param  array $replaces
   * @param  int $index
   * @throws \Orcses\PhpLib\Exceptions\InvalidArgumentException
   * @return array
   */
  function success(string $report_key, array $info = [], array $replaces = null, int $index = null)
  {
    if( ! report()->has( $report_key )){
      throw new \Orcses\PhpLib\Exceptions\InvalidArgumentException(
        "Report Key '{$report_key}' does not exist"
      );
    }

    $code = $index > 0 ? "0{$index}" : 0;

    $code = $replaces ? [$code, $replaces] : $code;

    return [$report_key, $code, (array) $info];
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


if (! function_exists('view')) {
  /**
   * Returns the full path to a view template file
   *
   * @param  string  $path
   * @return mixed
   */
  function view(string $path)
  {
    $file_path = str_replace('.', DIRECTORY_SEPARATOR, $path);

    return realpath(base_dir() . '/resources/' . $file_path . '.html' );
  }
}


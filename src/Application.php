<?php

namespace Orcses\PhpLib;

use Error;
use Exception;
use RuntimeException;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Exceptions\ClassNotFoundException;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;
use Orcses\PhpLib\Exceptions\RoutesFileNotFoundException;
use Orcses\PhpLib\Exceptions\ConfigurationFileNotFoundException;


final class Application extends Foundation
{
  private static $instance, $bindings = [];

  protected static $routes, $route_names;

  protected $config, $namespace;


  /**
   * Returns same App instance (singleton)
   * This is the only way to start the App since App::__construct() is private
   * @param string $base_dir
   * @return $this
   */
  public static function instance(string $base_dir = '')
  {
    if( ! static::$instance) {
      static::$instance = new static($base_dir);
    }

    return static::$instance;
  }


  /**
   * To maintain just one instance, use App::instance() to obtain as singleton
   * @param string $base_dir
   */
  protected function __construct($base_dir)
  {
    parent::__construct($base_dir);

    /**
     * Load the app configurations
     */
    $this->load_configurations();

    $this->set_default_timezone();

    /**
     * Each incoming API call has payload ['op' => '{value}'] where {value} is a two-character key
     * that maps the Operation to its Controller method.
     *
     * The Controllers are loaded first since load_request_op_groups() depends on controllers_map
     */
    static::load_routes_controllers();

    // [Optional] Specify custom error handler for MysqlQuery operations
    if($this->config['exceptions.handler'] === 'log_info'){
      $this->use_log_info_handler();
    }
  }


  protected function load_configurations()
  {
    try {
      $config = require ( $this->appRoot() . '/config/app.php'.'' );

      $this->config = Arr::toDotNotation($config);
    }
    catch (Exception $e){
      throw new ConfigurationFileNotFoundException();
    }
  }


  public static function environment()
  {
    return env('APP_ENV');
  }


  public static function isLocal()
  {
    return static::environment() === 'local';
  }


  public static function routes()
  {
    return static::$routes;
  }


  public static function route_names()
  {
    return static::$route_names;
  }


  public static function getControllerFromName(string $name)
  {
    if(array_key_exists($name, $route_names = static::route_names())){

      return static::getController( $route_names[ $name ] );
    }

    throw new Exception("Route with name '$name' does not exist");
  }


  public static function getController($op)
  {
    if(array_key_exists($op, $routes = static::routes())){
      return $routes[ $op ];
    }

    throw new Exception("Operation with key '$op' does not exist");
  }


  public static function get_op($controller)
  {
    $controllers_map = static::route_names();

    if(array_key_exists($controller, $controllers_map)){
      return $op = $controllers_map[ $controller ];
    }

    throw new Exception("Specified controller '$controller' does not exist");
  }


  protected function set_default_timezone()
  {
    date_default_timezone_set( $this->config['timezone'] );
  }


  private static function use_log_info_handler()
  {
    $target_classes = config('exceptions.target_classes') ?? [];

    foreach ($target_classes as $class){
      $log_info_parameters = [
        'message' => [
          'function' => [$class, 'getErrorMessage']
        ],
        'callback' => [
          'function' => [Response::class, 'handle'],
          'parameters' => [['App', 3]]
        ]
      ];

      $class::setErrorHandler([
        'function' => 'log_info',
        'parameters' => $log_info_parameters
      ]);
    }
  }


  public function set_CORS_allowed_Urls()
  {
    $envUrls = explode(',', $this->config['http.cors.allow'] ?? '');

    Response::set_CORS_allowed_Urls($envUrls);
  }


  /**
   * @param \Orcses\PhpLib\Request $request
   * @return \Orcses\PhpLib\Response
   */
  public function handle(Request $request)
  {
    $captured = $request->captured();

    [$input, $errors] = Arr::pickOnly($captured, ['input', 'errors'], false);
//    dd($input, $errors);

    if($errors){
      $result = Result::prepare([ 'validation', 1, ['v' => $errors] ]);
    }
    else {
      $controller = static::getController( $input['op'] );

      $result = '';
    }

    return Response::dispatch($result);
  }


  public function getAppNamespace(){
    if (! is_null($this->namespace)) {
      return $this->namespace;
    }

    $composer = json_decode(file_get_contents(full_app_dir() .'/composer.json'), true);

    foreach ((array) arr_get($composer, 'autoload.psr-4') as $namespace => $path) {
      foreach ((array) $path as $pathChoice) {
        dd($namespace, $path, $pathChoice, realpath($this->appRoot()), realpath(full_app_dir().'/'.$pathChoice));
        if (realpath($this->appRoot()) == realpath(full_app_dir().'/'.$pathChoice)) {
          return $this->namespace = $namespace;
        }
      }
    }

    throw new RuntimeException('Unable to detect application namespace.');
  }


  public function getNamespace(string $for = '')
  {
    static::getAppNamespace();

    switch (strtolower($for)){
      case 'controllers' : {
        $append = 'Controllers'; break;
      }
      case 'models' : {
        $append = 'Models'; break;
      }
      default: $append = '';
    }

    if($for && ! $append){
      throw new InvalidArgumentException($for, __CLASS__ .'::'.__METHOD__);
    }

    return $this->namespace . ($append ? $append .'\\' : '');
  }


  public function getClassAndMethod(string $controller)
  {
    return explode('@', $controller, 2);
  }


  public function getControllerInstanceFromOp($op)
  {
    return static::getControllerInstance(
      static::getController( $op )
    );
  }


  public function getControllerInstance($controller)
  {
    [$class] = static::getClassAndMethod($controller);

    $name_spaced_class = static::getNamespace('Controllers') . $class;

    return static::instance()->build( $name_spaced_class );
  }


  // ToDo: create and use facades (like in Aoo::make()) instead of full class namespaces
  public function build(string $class_name)
  {
    if(! array_key_exists($class_name, static::$bindings)){
      try {
        // ToDo: how about class dependencies???  -  use Reflection
        $class_instance = new $class_name();

        static::$bindings[ $class_name ] = $class_instance;
      }
      catch (Error $e){}
      catch (Exception $e){}

      if( ! empty($e)){
        throw new ClassNotFoundException( $class_name );
      }
    }

    return static::$bindings[ $class_name ];
  }


  private function load_routes_controllers()
  {
    if(!static::$routes){
      try {
        $routes_map = require ( $this->appRoot() . '/routes/api.php'.'' );
      }
      catch (Exception $e){
        throw new RoutesFileNotFoundException();
      }

      foreach($routes_map as $i => $values){

        foreach($values as $key => $value){
          $op = $i . $key;

          [$controller, $name] = is_array($value) ? $value : [$value, ''];

          if($name && ! is_numeric($name)){
            static::$route_names[ $name ] = $op;
          }

          static::$routes[ $op ] = $controller;
        }
      }
    }
  }


}

<?php

namespace Orcses\PhpLib;


use Net\Middleware;
use Orcses\PhpLib\Utility\FileUtil;
use RuntimeException;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Access\Auth;
use Orcses\PhpLib\Models\Model;
use Orcses\PhpLib\Routing\Router;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;
use Orcses\PhpLib\Exceptions\Routes\InvalidRouteActionException;


final class Application extends Foundation
{
  private static $instance;

  protected $namespace;

  /** @var \Orcses\PhpLib\Routing\Router */
//  protected $router;

  /** @var \Orcses\PhpLib\Routing\Controller */
  protected $controller;


  /**
   * To maintain just one instance, use App::instance() to obtain as singleton
   * @param string $base_dir
   */
  protected function __construct($base_dir)
  {
    if( ! static::$instance) {
      static::$instance = $this;
    }

    pr(['usr' => __FUNCTION__, '$base_dir' => $base_dir, 'time' => time(), 'static::$instance' => static::$instance]);

    parent::__construct($base_dir);

    // Load App and System reports
    Response::loadReportMessages();

    $this->set_default_timezone();

    // [Optional] Specify custom error handler for MysqlQuery operations
    if($this->config('exceptions.handler') === 'log_info'){
      $this->use_log_info_handler();
    }

//    $this->router = $this->make('Router');
//    $this->router = $this->make(\Orcses\PhpLib\Routing\Router::class);

    $this->controller = $this->make('Controller');
  }


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


  public function config(string $key = '', string $file = 'app')
  {
    if( ! array_key_exists($file, $this->config)){

      throw new InvalidArgumentException("Config group '{$file}' does not exist'");
    }

    if($key){
      return arr_get( $this->config[ $file ], $key );
    }

    return $this->config;
  }


  public static function environment()
  {
    return env('APP_ENV');
  }


  public static function isLocal()
  {
    return static::environment() === 'local';
  }


  protected function set_default_timezone()
  {
    date_default_timezone_set( $this->config('timezone') );
  }


  private function use_log_info_handler()
  {
    $target_classes = $this->config('exceptions.target_classes');

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


  /**
   * @param \Orcses\PhpLib\Request $request
   * @return \Orcses\PhpLib\Response
   */
  public function handle(Request $request)
  {
    Router::loadRoutes();

    // Get matching Route
    if( ! $route = Router::find( ...$request->currentRouteParams() )){
      pr(['usr' => __FUNCTION__, 'checks 111 No $route' => $route]);

      $request::abort();
      exit;
    }

    [$target, $arguments, $attributes] = $route->props(['target', 'parameters', 'attributes']);

    // Bind route parameters into request\][p
    $request->setParams( $arguments );

    pr(['usr' => __FUNCTION__, '$target' => $target, '$arguments' => $arguments, '$attributes' => $attributes]);


    // Abort request if controller is invalid
    if( ! $target){

      [$route_method, $route_uri] = $route->props(['method', 'uri']);

      $route_params = strtoupper($route_method) .' '. $route_uri;
      pr(['usr' => __FUNCTION__, 'checks 222 No $target' => $target, '$route_params' => $route_params]);

      throw new InvalidRouteActionException( $target, $route_params);
    }

    pr(['usr' => __FUNCTION__, '$request input b4 Middleware' => $request->input()]);


    // Run specified Middleware
    if( isset($attributes['middleware'])){

      $request = Middleware::run($request, $attributes['middleware']);
    }
    pr(['usr' => __FUNCTION__, '$request input after Middleware' => $request]);


    // Pin the final Request state
    $request->pinCurrentState();


    if($target instanceof \Closure){

      $output = $target->call($this, $arguments);
      pr(['usr' => __FUNCTION__, '$output Closure' => $output, '$output class' => is_object($output) ? get_class($output) : '']);

      if( is_a($output,Response::class)){
        // Already packaged; ready to send
        return $output;
      }
//      elseif( ! is_a($output,Result::class)){

        $result = Result::prepare( $output );
        pr(['usr' => __FUNCTION__, '$result 333' => $result]);
//      }
    }
    else {
      [$controller, $method] = $this->controller->getClassAndMethod($target);

      // Resolve controller from DI Container
      $controller_class = $this->controller->makeInstanceFor($controller);
      pr(['usr' => __FUNCTION__, '$controller' => $controller, '$method' => $method]);

      // Resolve controller method from DI Container
      /** @var \ReflectionMethod $reflectorMethod */
      [$reflectorMethod, $dependencies] = $this->container->resolveMethod($controller_class, $method);
      pr(['usr' => __FUNCTION__, '$controller_class' => get_class($controller_class)]);

      if( ! empty($e)){
        throw new InvalidRouteActionException( $target );
      }

      // Format Dependencies
      foreach ($dependencies as $parameter_name => &$dependency){

        if(is_subclass_of($dependency, Request::class)){
          /**@var Request $dependency */

          // Authorize request, else, abort
          if(method_exists($dependency, 'authorize')){

            $request->authorizeWith( $dependency->authorize() );
          }

          // Validate request, else, abort
          if(method_exists($dependency, 'rules')){

            $request->validateWith( $dependency->rules() );
          }
          pr(['usr' => __FUNCTION__,'$input 111' => $request->input(), '$request->errors()' => $request->errors()]);

          // Transform request
          // ToDo: implement this, or use middleware
          if(method_exists($dependency, 'transform')){

//            $request->transformWith( $dependency->transform() );
          }

          // If all is fine, hydrate the child request with the base Request contents
          $dependency->hydrate();

        }
        elseif(is_a($dependency, Model::class)){
          /**@var Model $dependency */

          // Inject Model dependencies into controller
          if(array_key_exists($parameter_name, $arguments)){

            $dependency = Model::newFromObj($dependency, $arguments[ $parameter_name ]);
          }
        }
      }
      pr(['usr' => __FUNCTION__,'$output' => $output??'', '$dependencies' => $dependencies, '$reflectorMethod' => !empty($reflectorMethod) ? get_class($reflectorMethod) : '']);

      if(empty($output) && ! empty($reflectorMethod)){
        $dependencies = array_values($dependencies);

        // Call Controller Method
        $output = $reflectorMethod->invokeArgs($controller_class, $dependencies);
      }

      $result = is_a($output,Result::class)
        ? $output
        : Result::prepare($output);  // ToDo: remove this
    }

    pr(['usr' => __FUNCTION__, '$result' => $result ?? '']);

    // Return packaged response to App index for dispatch
    return Response::package( $result );
  }


  protected function setAppNamespace()
  {
    if( ! $composer = FileUtil::loadJsonResource($this->baseDir() .'/composer.json')){

      throw new RuntimeException("Please, cross-check 'composer.json' for any errors");
    }

    foreach ((array) Arr::get($composer, 'autoload.psr-4') as $namespace => $path) {

      foreach ((array) $path as $pathChoice) {

        if (realpath($this->appDir()) == realpath($this->baseDir().'/'.$pathChoice)) {
          return $this->namespace = $namespace;
        }
      }
    }

    throw new RuntimeException('Unable to detect application namespace.');
  }


  public function getAppNamespace(){
    if (is_null($this->namespace)) {
      $this->setAppNamespace();
    }

    return $this->namespace;
  }


  public function getNamespace(string $for = ''){
    $this->getAppNamespace();

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


}

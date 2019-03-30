<?php

namespace Orcses\PhpLib;

use Orcses\PhpLib\Access\Auth;
use Orcses\PhpLib\Models\Model;
use RuntimeException;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;


final class Application extends Foundation
{
  private static $instance;

  protected $namespace;

  /** @var \Orcses\PhpLib\Routing\Router */
  protected $router;

  /** @var \Orcses\PhpLib\Routing\Controller */
  protected $controller;


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

    $this->set_default_timezone();

    // [Optional] Specify custom error handler for MysqlQuery operations
    if($this->config['exceptions.handler'] === 'log_info'){
      $this->use_log_info_handler();
    }

    $this->router = $this->make('Router');
//    $this->router = $this->make(\Orcses\PhpLib\Routing\Router::class);

    $this->controller = $this->make('Controller');
  }


  public function config(string $key = ''){
    if($key){
      return $this->config[ $key ] ?? null;
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
    date_default_timezone_set( $this->config['timezone'] );
  }


  private function use_log_info_handler()
  {
    $target_classes = $this->config['exceptions.target_classes'];

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
    $this->router->loadRoutes();

    [$method, $uri, $route_space] = [$request->method(), $request->uri(), $request->routeSpace()];

    // Get Matching Route
    [$controller, $arguments] = $this->router->find( $method, $uri, $route_space );

    // Abort request if controller is invalid
    if( ! $controller){
      pr('checks 111');
      $request::abort();
    }
    elseif($controller instanceof \Closure){
      pr('checks 222');

      $result = $controller->call($this, $arguments);
    }
    else {
      pr('checks 333');
      [$controller, $method] = $this->controller->getClassAndMethod($controller);

      // Resolve controller from DI Container
      $controller_class = $this->controller->makeInstanceFor($controller);

      // Resolve controller method from DI Container
      /** @var \ReflectionMethod $reflectorMethod */
      [$reflectorMethod, $dependencies] = $this->container->resolveMethod($controller_class, $method);

      // Validate and check Auth


      // Format Dependencies
      foreach ($dependencies as $parameter_name => &$dep){
//        $class_name = basename( get_class($dep));

        if(is_a($dep, Request::class)){
//          dd($class_name, $dep->input());

          if(method_exists($dep, 'authorize') && ! $dep->authorize()){
            $output = Auth::error(Auth::NOT_AUTHORIZED);
            break;
          }

          if(method_exists($dep, 'rules')){
            $request->validatesWith( $dep->rules() );

            if($errors = $request->errors()){
              $output = ['validation', 1, $errors];
              break;
            }
          }

//          $dep = $request->instance();
//          dd($dep->input());

          /*dd(
            ['dep parameter name' => $parameter_name],
            ['dep class name' => $class_name],
            ['Request authorize' => method_exists($dep, 'authorize')],
            ['Request transform' => method_exists($dep, 'transform')],
            ['Request rules' => method_exists($dep, 'rules')]
          );*/
        }
        elseif(is_a($dep, Model::class)){
//          dd('$dep Model', $parameter_name, $class_name);

          if(array_key_exists($parameter_name, $arguments)){
            $dep = Model::newFromObj($dep, $arguments[ $parameter_name ]);
          }
        }

      }

      if(empty($output)){
//      dd('$dependencies', $dependencies);
        $dependencies = array_values($dependencies);

        // Call Controller Method
        $output = $reflectorMethod->invokeArgs($controller_class, $dependencies);
      }

//      dd(
//        ['controller name' => $controller],
//        ['controller class' => $controller_class],
//        ['method name' => $method],
//        ['method arguments' => $arguments],
//        ['method reflector' => $reflectorMethod],
//        ['method dependencies' => $dependencies],
//        ['method $errors' => $errors]
//      );

      pr(['$output' => $output]);
      $result = Result::prepare($output);
      pr(['$result' => $result]);
    }

//    dd('$result', $result);

    /*$input = $request->input();
      dd($input);

    foreach($arguments as $key => $arg){
      $arguments[ $key ] = $input[ $key ] ?? null;
    }*/


//    dd('$controller', $controller);


    // Check Access
//    $this->auth()->check();

    // Check other Middleware


    // Call Controller method

    if(!$result) dd('No controller', $method, $uri, $route_space);

//      dd('$result', $result);
    return Response::package( $result );
  }


  protected function setAppNamespace(){
    $composer = json_decode(file_get_contents($this->baseDir() .'/composer.json'), true);

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

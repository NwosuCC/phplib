<?php

namespace Orcses\PhpLib;

use Exception;
use RuntimeException;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Routing\Route;
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


  public function config(string $key){
    return $this->config[ $key ] ?? null;
  }


  public static function environment()
  {
    return env('APP_ENV');
  }


  public static function isLocal()
  {
    return static::environment() === 'local';
  }


  public static function get_op($controller)
  {
    $controllers_map = Route::names();

    if(array_key_exists($controller, $controllers_map)){
      return $op = $controllers_map[ $controller ];
    }

    throw new Exception("Specified controller '$controller' does not exist");
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

    // Get Matching Route
    [$controller, $arguments] = $this->router->find( $request->method(), $request->uri() );

    if( ! $controller){
      $result = $request->abort();
    }
    else if(is_callable($controller)){
      $result = call_user_func($controller, $arguments);
    }
    else {
      // ToDo: try DI of $arguments here
      [$controller, $method] = $this->controller->getClassAndMethod($controller);

      $controller_class = $this->controller->makeInstanceFor($controller);

      // Resolve controller method
      $method = $this->container->resolveMethod($controller_class, $method, $arguments);

//      call_user_func([$controller_class, $method], $arguments);


      dd($controller, $controller_class, $method, $arguments);

      $result = null;
    }


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



    return Response::package( $result );
  }
/*public function handle(Request $request)
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
  }*/


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

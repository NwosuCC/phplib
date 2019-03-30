<?php

namespace Orcses\PhpLib\Routing;


use Exception;
use Orcses\PhpLib\Exceptions\Routes\DuplicateRouteNamesException;
use Orcses\PhpLib\Exceptions\Routes\DuplicateRoutesException;
use Orcses\PhpLib\Exceptions\Routes\MethodNotSupportedException;
use Orcses\PhpLib\Exceptions\Routes\RouteNotYetRegisteredException;
use Orcses\PhpLib\Exceptions\Routes\RoutesFileNotFoundException;
use Orcses\PhpLib\Utility\Arr;


class Router
{
  /** @var \Orcses\PhpLib\Application */
  protected $app;

  /** @var $this */
  protected static $instance;


  public const WEB = 'web', API = 'api';

  public const LOGIN = 'login';


  protected static $route_file;

  protected static $loaded = [], $exceptions = [];

  protected static $route_spaces = [ self::WEB, self::API ];

  protected static $base_route;

  protected static $methods = [
    'get', 'post', 'put', 'patch', 'delete', 'options'
  ];

  protected static $routes = [], $route_names = [];

  protected $key, $name;


  public function __construct()
  {
    self::$instance = $this;
  }


  public static function router(){
    return static::$instance;
  }


  protected static function currentRouteFile()
  {
    return static::$route_file;
  }


  protected function methods()
  {
    return static::$methods;
  }


  public static function routes()
  {
    return static::$routes;
  }


  public static function names()
  {
    return static::$route_names;
  }


  protected function setRouteFile(string $route_file)
  {
    static::$route_file = $route_file;
  }


  protected static function baseRoute()
  {
    if( ! static::$base_route){
      static::$base_route = '\\' . ltrim( base_dir(), realpath($_SERVER['DOCUMENT_ROOT']));
    }

    return real_url(static::$base_route);
  }


  protected static function routePath(string $uri)
  {
    $clean_uri = strtolower( ltrim( real_url($uri)));

    $route_path = '\\' . ltrim($clean_uri, static::baseRoute());

    return real_url($route_path);
  }


  protected function addException(string $exception_type, string $value = null, array $arguments = [])
  {
    if( ! array_key_exists($value, static::$exceptions[ $exception_type ] ?? [])){
      static::$exceptions[ $exception_type ][ $value ] = $arguments;
    }

    return true;
  }


  protected function validateRoute($file, $key, array $arguments)
  {
    [$method, $uri] = $arguments;

    if( ! in_array($method, $this->methods())){
      $error = $this->addException(MethodNotSupportedException::class, $method, [$method]);
//      throw new MethodNotSupportedException($method);
    }
    elseif( isset(static::$routes[ $file ][ $method ][ $uri ]) ){
      $error = $this->addException(DuplicateRoutesException::class, $key, [$file, $method, $uri]);
      //      throw new DuplicateRoutesException($file, $method, $uri);
    }

    return empty($error);
  }


  protected function register(string $method, string $uri, $target, array $parameters = [])
  {
    $file = $this->currentRouteFile();

    $key = join('.', [$file, $method, $uri]);

    if( ! $this->validateRoute($file, $key, [$method, $uri])){
      return null;
    }

    static::$routes[ $file ][ $method ][ $uri ] = [$target, $parameters];

    return $key;
  }


  protected function validateRouteName(string $name)
  {
    if( ! $key = $this->key or ! Arr::get(static::routes(), $key) ){
      $error = $this->addException(RouteNotYetRegisteredException::class, $key, [$name]);
    }
    elseif( isset(static::$route_names[ $name ]) ){
      $error = $this->addException(DuplicateRouteNamesException::class, $name, [$name]);
    }

    return empty($error);
  }


  protected function setName(string $name)
  {
    if( ! $this->validateRouteName($name)){
      return null;
    }

    static::$route_names[ $name ] = $this->key;
  }


  public static function find(string $method, string $uri, string $route_space = null)
  {
    $method = strtolower($method);

    $uri = static::routePath($uri);

    if( ! $route_space){
      $route_space = static::currentRouteFile();
    }

    return static::routes()[ $route_space ][ $method ][ $uri ] ?? null;
  }


  public static function findByName(string $name)
  {
    $key = static::names()[ $name ] ?? null;

    return $key ? Arr::get(static::routes(), $key) : null;
  }


  protected static function loadNamespaceRoutes()
  {
    try {
      $file_path = app()->baseDir() . '/routes/'. static::$route_file .'.php';

      // To allow Routes class load the various defined routes
//      usleep(10000);

      if(file_exists($file_path)){
        static::$loaded[] = static::$route_file;

        require ( ''.$file_path.'' );
      }
    }
    catch (Exception $e){}
  }


  protected static function throwExceptions(){
    foreach (static::$exceptions as $exception_type => $exceptions){

      foreach ($exceptions as $value => $arguments){
        throw new $exception_type(...$arguments);
      }
    }
  }


  public function loadRoutes()
  {
    if( ! static::$routes){

      foreach (static::$route_spaces as $namespace){
        static::$route_file = $namespace;

        static::loadNamespaceRoutes();
      }

      if( ! static::$loaded){
        throw new RoutesFileNotFoundException();
      }
      elseif (static::$exceptions){
        static::throwExceptions();
      }

      // ToDo: cache all routes after loading


      /*foreach($routes_map as $i => $values){

        foreach($values as $key => $value){
          $op = $i . $key;

          [$controller, $name] = is_array($value) ? $value : [$value, ''];

          if($name && ! is_numeric($name)){
            static::$route_names[ $name ] = $op;
          }

          static::$routes[ $op ] = $controller;
        }
      }*/
    }
  }


}
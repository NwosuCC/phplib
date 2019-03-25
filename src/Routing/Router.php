<?php

namespace Orcses\PhpLib\Routing;


use Exception;
use Orcses\PhpLib\Exceptions\RoutesFileNotFoundException;


class Router
{
  /** @var \Orcses\PhpLib\Application */
  protected $app;

  /** @var $this */
  protected static $instance;


  protected const WEB = 'web', API = 'api';

  protected $route_space, $loaded = [], $route_spaces = [
    self::WEB, self::API
  ];


  protected $base_route;

  protected $methods = [
    'get', 'post', 'put', 'patch', 'delete', 'options'
  ];

  protected $routes = [], $route_names = [];


  public function __construct()
  {
    self::$instance = $this;
  }


  public static function router(){
    return static::$instance;
  }


  public function currentNamespace()
  {
    return $this->route_space;
  }


  public function methods()
  {
    return $this->methods;
  }


  public function routes()
  {
    return $this->routes;
  }


  public function names()
  {
    return $this->route_names;
  }


  public function setRouteSpace(string $route_space)
  {
    $this->route_space = $route_space;
  }


  protected function baseRoute(){
    if( ! $this->base_route){
      $this->base_route = '\\' . ltrim( base_dir(), realpath($_SERVER['DOCUMENT_ROOT']));
    }

    return real_url($this->base_route);
  }


  protected function routePath(string $uri){
    $clean_uri = strtolower( ltrim( real_url($uri)));

    $route_path = '\\' . ltrim($clean_uri, $this->baseRoute());

    return real_url($route_path);
  }


  protected function register(string $method, string $uri, $target, array $parameters = []){
    $namespace = $this->currentNamespace();

    $this->routes[ $namespace ][ $method ][ $uri ] = [$target, $parameters];
  }


  public function find(string $method, string $uri)
  {
    $method = strtolower($method);

    $uri = $this->routePath($uri);

    $namespace = $this->currentNamespace();

    return $this->routes()[ $namespace ][ $method ][ $uri ] ?? null;
  }


  protected function loadNamespaceRoutes(){
    try {
      $file_path = app()->baseDir() . '/routes/'. $this->route_space .'.php';

      // To allow Route class load the various defined routes
      usleep(1000);

      if(file_exists($file_path)){
        require ( ''.$file_path.'' );

        $this->loaded[] = $this->route_space;
      }
    }
    catch (Exception $e){}
  }


  public function loadRoutes(){
    if( ! $this->routes){

      foreach ($this->route_spaces as $namespace){
        $this->route_space = $namespace;

        $this->loadNamespaceRoutes();
      }

      if( ! $this->loaded){
        throw new RoutesFileNotFoundException();
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
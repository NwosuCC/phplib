<?php

namespace Orcses\PhpLib\Routing;


use Exception;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;
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

  protected static $LOGIN_ROUTE_NAME;


  /**
   * Attributes associated with specific route groups  e.g 'middleware', 'namespace', etc
   */
  protected $attributes = [];

  protected static $allowed_attributes = [
    'middleware', 'namespace', 'prefix', 'name'
  ];

  protected static $groups = [];

  protected static $group_attributes = [];


  protected static $route_file;

  protected static $loaded = [], $exceptions = [];

  protected static $route_spaces = [ self::WEB, self::API ];

  protected static $base_route;

  protected static $methods = [
    'get', 'post', 'put', 'patch', 'delete', 'options'
  ];

  protected static $routes = [], $route_names = [];

  protected $id, $file, $method, $uri, $key, $name, $target, $parameters;


  public function __construct()
  {
    $this->id = random_bytes(11);

    self::$instance = $this;
  }


  public static function router(){
    return static::$instance;
  }


  protected static function currentRouteFile()
  {
    return static::$route_file;
  }


  protected static function currentGroupAttributes()
  {
    return static::$group_attributes;
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
    }
    elseif( isset(static::$routes[ $file ][ $method ][ $uri ]) ){

      $error = $this->addException(DuplicateRoutesException::class, $key, [$file, $method, $uri]);
    }

    return empty($error);
  }


  protected function addGroup(callable $callback)
  {
    $this->file = $this->currentRouteFile();

    static::$groups[ $this->id ] = [$this, $callback];
  }


  protected function addAttributes(array $attributes)
  {
    foreach($attributes as $name => $value){
      $this->attribute($name, $value);
    }
  }


  protected function attribute(string $key, $value)
  {
    if( ! in_array($key, static::$allowed_attributes)){
      $this->addException(InvalidArgumentException::class, $key, __FUNCTION__);
      return;
    }

    $array_value = is_array( $value );

    foreach((array) $value as $value){

      $array_value
        ? $this->attributes[ $key ][] = $value
        : $this->attributes[ $key ] = $value;

      if($this->target){
        $function_name = 'setRoute' . ucfirst($key);

        if(method_exists($this, $function_name)){
          $this->{$function_name}($value);
        }
      }
    }

    if($this->target){
      pr(['lgc' => __FUNCTION__, 'key' => $this->key, 'name' => $this->name, 'target' => $this->target, 'attributes' => $this->attributes]);
    }
  }


  protected function register(string $method, string $uri, $target, array $parameters = [])
  {
    $file = $this->currentRouteFile();

    $key = join('.', [$file, $method, $uri]);

    if( ! $this->validateRoute($file, $key, [$method, $uri])){
      return;
    }

    // ToDo: refactor this - create a dedicated class for route
    $this->key = $key;
    $this->uri = $uri;
    $this->file = $file;
    $this->method = $method;
    $this->target = $target;
    $this->parameters = $parameters;
    $this->addAttributes( $this->currentGroupAttributes() );

    static::$routes[ $file ][ $method ][ $uri ] = $this;
  }


  protected function validateRouteName(string $name)
  {
    if( stristr($name, '.') !== false ){
      $arguments = ["Valid route name must not have a dot (.), {$name} given"];

      $error = $this->addException( InvalidArgumentException::class, $name, $arguments );
    }
    elseif( ! $key = $this->key or ! Arr::get(static::routes(), $key) ){

      $error = $this->addException(RouteNotYetRegisteredException::class, $key, [$name]);
    }
    elseif( isset(static::$route_names[ $name ]) ){

      $error = $this->addException(DuplicateRouteNamesException::class, $name, [$name]);
    }

    return empty($error);
  }


  // Called from Auth::attempt()
  public static function getLoginRouteName()
  {
    pr(['lgc' => __FUNCTION__, 'login name' => static::$LOGIN_ROUTE_NAME]);
    return static::$LOGIN_ROUTE_NAME;
  }


  public static function setLoginRouteName(array $route_params)
  {
    if(static::$LOGIN_ROUTE_NAME){
      return;
    }

    $route = static::find( ...$route_params );

    static::$LOGIN_ROUTE_NAME = $route ? $route->name : null;
  }


  // Called from Router::attribute()
  protected function setRouteName(string $name)
  {
    if( ! $this->validateRouteName($name)){
      unset( $this->attributes['name'] );
      return;
    }

    $prefix = isset($this->attributes['prefix']) ? $this->attributes['prefix'] : '';

    $this->name = $prefix . $name;

    static::$route_names[ $name ] = $this->key;
  }


  // Called from Router::attribute()
  protected function setRoutePrefix(string $prefix)
  {
    if( ! $this->name || ! $this->target || $this->target instanceof \Closure){
      // A blank name or a null|Closure target cannot have 'prefix'
      unset( $this->attributes['prefix'] );
      return;
    }

    $this->name = ucfirst($prefix) . $this->name;
  }


  // Called from Router::attribute()
  protected function setRouteNamespace(string $name_space)
  {
    if( ! $this->target || $this->target instanceof \Closure){
      // A null|Closure target cannot have 'namespace'
      unset( $this->attributes['namespace'] );
      return;
    }

    $this->target = ucfirst($name_space) . '\\' . $this->target;
  }


  /**
   * @param string $method
   * @param string $uri
   * @param string $route_space [optional]
   * @return  null|Router $route
   */
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

    [ $route_space, $method, $uri ] = explode('.', $key);

    return static::find( $method, $uri, $route_space);
  }


  public function props(array $props){
    $props_values = [];

    foreach ($props as $prop){
      $props_values[] = $this->{$prop} ?? null;
    }

    return $props_values;
  }


  protected static function loadNamespaceRoutes()
  {
    try {
      $file_path = base_dir() . '/routes/'. static::$route_file .'.php';

      // To allow Routes class load the various defined routes
//      usleep(10000);

      if(file_exists($file_path)){
        static::$loaded[] = static::$route_file;

        // ToDo: pass these file routes into a closure to load later as route Group
        /* E.g
            Route::group([], function(){
                require ( ''.$file_path.'' );
            })->middleware('web');
         */

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


  public static function loadRoutes()
  {
    if( ! static::$routes){

      foreach (static::$route_spaces as $namespace){
        static::$route_file = $namespace;

        static::loadNamespaceRoutes();
      }

      foreach (static::$groups as $id => [$route, $callback]){

        static::$route_file = $route->file;

        static::$group_attributes = $route->attributes;

        $callback->call( $route );

        /*$route_callback = function(Route $route) use($callback){

          $route->post('aa', 'sss');

          $callback( $route );

        };*/
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
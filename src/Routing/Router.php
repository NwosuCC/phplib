<?php

namespace Orcses\PhpLib\Routing;


use Exception;
use Orcses\PhpLib\Exceptions\FileNotFoundException;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;
use Orcses\PhpLib\Exceptions\Routes\DuplicateRoutesException;
use Orcses\PhpLib\Exceptions\Routes\MethodNotSupportedException;
use Orcses\PhpLib\Exceptions\Routes\DuplicateRouteNamesException;
use Orcses\PhpLib\Exceptions\Routes\RouteNotYetRegisteredException;
use Orcses\PhpLib\Request;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Utility\Str;


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

  protected static $routes = [], $param_routes = [], $route_names = [];

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
      $base_route = '\\' . ltrim( base_dir(), realpath($_SERVER['DOCUMENT_ROOT']));

      static::$base_route = real_url( $base_route );
    }

    return static::$base_route;
  }


  protected static function routePath(string $uri)
  {
    $clean_uri = strtolower( ltrim( real_url($uri)));

    $route_path = '\\' . Str::stripLeadingChar($clean_uri, static::baseRoute().'/');

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

    if(stripos($uri, '{') !== false){
      $this->registerParams();
    }

    static::$routes[ $file ][ $method ][ $uri ] = $this;
  }


  protected function registerParams()
  {
    [$file, $method, $uri] = [$this->file, $this->method, $this->uri];

    if($duplicate = static::filterParamRoute([$file, $method, $uri], true)){

      $duplicate = array_map(function ($val){ return implode('/', $val); }, $duplicate);

      [$route, $duplicate_route] = $duplicate;

      $arguments = ["The supplied route '{$route}' possibly has a duplicate: '{$duplicate_route}''"];

      $this->addException( DuplicateRoutesException::class, $uri, $arguments );

      return;
    }

    $uri_parts = Arr::stripEmpty( explode('/', $uri));

    $param_routes = [];

    foreach (array_reverse($uri_parts) as $i => $part){
      $item = ! empty($param_routes) ? $param_routes : 1234;

      $param_routes = [$part => $item];
    }

    $param_routes = [ $file => [ $method => $param_routes]];

    static::$param_routes = array_merge_recursive( static::$param_routes, $param_routes );
  }


  /**
   * Narrow down the routes array to only the routes with similar base paths
   * E.g '/posts/...' where $part === 'post'
   * @param array $uri_parts
   * @param array $param_routes
   * @return array
   */
  protected static function hasCommonBasePath(array $uri_parts, array $param_routes)
  {
    $stripped_parts = [];

    foreach ($uri_parts as $i => $part){

      if( ! isset($param_routes[ $part ])){
        break;
      }

      $param_routes = $param_routes[ $part ];

      // Strip the 'part's that (are set) the param-routes have in common
      unset( $uri_parts[ $i ] );

      // Collate the stripped parts to merge back into the returned params
      $stripped_parts[ $i ] = $part;
    }

    return [ array_values($uri_parts), $param_routes, $stripped_parts ];
  }


  /**
   * Flatten the keys using Dot Notation, then, filter the array dimensions (2-dim, 3-dim, etc)
   * E.g '/posts/...' where $part === 'post'
   * @param array $param_routes
   * @param int $dimension
   * @return array
   */
  protected static function hasEqualDimension(array $param_routes, int $dimension)
  {
    $param_routes_keys = array_keys( Arr::toDotNotation( $param_routes ));

    // Pick only the param-routes whose key dimension equals the dimensions of the target $uri
    $param_routes_keys = array_filter( $param_routes_keys, function($param) use($dimension)
    {
      $param_count = count( explode('.', $param));

      return $param_count === $dimension;
    });

    if($param_routes_keys = array_values($param_routes_keys)){

      foreach ($param_routes_keys as $i => $p_route){
        $param_routes_keys[ $i ] = Arr::stripEmpty( explode('.', $p_route));
      }

      return $param_routes_keys;
    }

    return $param_routes_keys;
  }


  /**
   * Recursively eliminate non-matching routes
   * a.) compare as normal path strings:
   *    If EQUAL, the route matches up to that point. Continue loop
   *    Else (if NOT EQUAL),
   *    b.) compare as placeholders:
   *        If BOTH ARE PLACEHOLDERS, the route may still match. Continue loop
   *        Else (NOT BOTH ARE PLACEHOLDERS), unset the route - it does not match the target $uri
   *
   * @param array $args
   * @param bool $reg
   * @return array
   */
  protected static function getUriBestMatch(array $args, bool $reg = false)
  {
    [$uri_parts, $param_routes_keys, $stripped_parts] = $args;

    $param_routes_keys_copy = $param_routes_keys;

    $regex = "/{[ ]*([^ {}]+)+[^{}]*}/";

    foreach ($uri_parts as $i => $uri_path){

      $uri_path_is_placeholder = preg_match($regex, $uri_path);

      foreach ($param_routes_keys_copy as $ip => $param_route){

        $param_route_path = $param_route[ $i ];

        if($uri_path === $param_route_path){
          // Current $param_route still matches target $uri. Ok for subsequent tests
          continue;
        }

        $param_route_path_is_placeholder = preg_match($regex, $param_route_path);

        // $uri_path_is_placeholder === true implies $reg === true
        $no_match_1 = ($uri_path_is_placeholder && ! $param_route_path_is_placeholder);

        $no_match_2 = (($reg && ! $uri_path_is_placeholder) || ! $param_route_path_is_placeholder);

        if( $no_match_1 || $no_match_2) {
          // Free this route from subsequent tests
          unset( $param_routes_keys[$ip] );
        }
      }
    }

    if( ! $params_best_match =  array_shift($param_routes_keys)){
      // There is no route params finally matching the target $uri
      return [];
    }

    foreach(array_reverse($stripped_parts) as $s_part){
      array_unshift($uri_parts, $s_part);
      array_unshift($params_best_match, $s_part);
    }

    return [ $uri_parts, $params_best_match ];
  }


  protected static function filterParamRoute(array $route_props, bool $reg = false)
  {
    [$route_space, $method, $uri] = $route_props;

    // Split the $uri path into its levels (paths)
    $uri_parts = array_values( Arr::stripEmpty( explode('/', $uri)));

    if( ! $param_routes = static::$param_routes[ $route_space ][ $method ] ?? null){
      return null;
    }


    // Get param-routes closely related to the target $uri
    $related_routes = static::hasCommonBasePath($uri_parts, $param_routes);

    [ $uri_parts, $param_routes, $stripped_parts ] = $related_routes;


    // From the closely related param-routes, get the routes having same key dimension as the target $uri
    $param_routes_keys = static::hasEqualDimension($param_routes, count($uri_parts));

    if( ! $param_routes_keys){
      // There is no route whose key dimension matches the target $uri
      return null;
    }

    // Final attempt to find a match for the target $uri
    return static::getUriBestMatch([$uri_parts, $param_routes_keys, $stripped_parts], $reg);
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


  public static function getLoginRouteName()
  {
    return static::$LOGIN_ROUTE_NAME;
  }


  public static function setLoginRouteName()
  {
    if(static::$LOGIN_ROUTE_NAME){
      return;
    }

    /** @var Request $request */
    $request = app(\Orcses\PhpLib\Request::class);

    $route = static::find( ...$request->currentRouteParams() );

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

    $route = static::routes()[ $route_space ][ $method ][ $uri ] ?? null;

    if( ! $route){
      $route = static::findByRouteParams($route_space, $method, $uri);
    }

    pr(['usr' => __FUNCTION__, 'given $route_space' => $route_space, 'method' => $method, 'uri' => $uri, 'found $route' => $route]);

    return $route;
  }


  protected static function findByRouteParams(string $route_space, string $method, string $uri)
  {
    if( ! $matches = static::filterParamRoute([$route_space, $method, $uri])){
      return null;
    }

    [$uri_parts, $uri_match] = $matches;

    $uri_match_path = '/' . implode('/', $uri_match);

    /** @var null|Router $route */
    if($route = static::routes()[ $route_space ][ $method ][ $uri_match_path ] ?? null){

      foreach ($uri_match as $i => $key){

        $key = str_replace('{', '', str_replace('}', '', $key), $key_is_placeholder);

        if($key_is_placeholder){
          $route->parameters[ $key ] = $uri_parts[ $i ];
        }
      }
    }

    return $route;
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

    return $file_path ?? null;
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

        $last_failed_file = static::loadNamespaceRoutes();
      }

      foreach (static::$groups as $id => [$route, $callback]){

        static::$route_file = $route->file;

        static::$group_attributes = $route->attributes;

        $callback->call( $route );
      }

      if( ! static::$loaded){
        throw new FileNotFoundException("Routes", $last_failed_file ?? '');
      }
      elseif (static::$exceptions){
        static::throwExceptions();
      }

      // ToDo: cache all routes after loading

    }
  }


}
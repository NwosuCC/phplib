<?php

namespace Orcses\PhpLib\Middleware;


use Orcses\PhpLib\Utility\Arr;
use RuntimeException;
use Orcses\PhpLib\Request;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;


class Middleware
{
  /** @var static */
  protected static $instance;

  protected $middleware = [];

  protected $middlewareGroup = [];


  protected static function instance()
  {
    if( ! static::$instance){
      static::$instance = app()->make( static::class );
    }

    return static::$instance;
  }


  public function getFromGroup(string $key)
  {
    if( ! is_null($middleware = Arr::get( $this->middlewareGroup, $key)) ){
      return $middleware;
    }

    throw new InvalidArgumentException("Middleware '{$key}' does not exist");
  }


  public static function run(Request $request, $group)
  {
    $group = (array) $group;

    $class = static::instance();

    $request_middleware = array_unique(
      array_merge( $class->middleware, $class->getMiddlewareList( $group ) )
    );

    $middleware_group = $class->buildGroup( $request_middleware );

    foreach($middleware_group as [$middleware, $next]){

      $request = call_user_func([$middleware, 'handle'], $request, $next);
    }

    return $request;
  }


  protected function buildGroup(array $request_middleware)
  {
    $middleware_group = [];

    $n = 2;

    while($class_name = array_pop($request_middleware) and $n--){

      $middleware = $this->getClass( $class_name );

      if( empty($next)){
        $next = function($request){ return $request; };
      }
      else {
        $next_middleware = end($middleware_group)[0];

        $next = function($request) use($next_middleware, $next){
          return call_user_func([$next_middleware, 'handle'], $request, $next);
        };
      }

      $middleware_group[] = [ $middleware, $next ];
    }

    return array_reverse($middleware_group);
  }


  protected function getClass(string $class_name)
  {
    $middleware = app()->make( $class_name );

    if( ! $middleware || ! is_a($middleware,self::class)){

      throw new RuntimeException(
        "Middleware class '{$middleware}' must extend the base class " . static::class
      );
    }

    return $middleware;
  }


  protected function getMiddlewareList(array $middleware_aliases)
  {
    $middleware_group = [];

    foreach ($middleware_aliases as $name){
      if( empty($name)){
        continue;
      }

      if(in_array($name, $this->middleware)){
        $middleware_group[] = $name;
      }
      elseif( ! is_array($middleware = static::getFromGroup( $name ))){
        $middleware_group[] = $middleware;
      }
      else {
        // Flatten the array and pick all its values
        $middleware = array_values( Arr::toDotNotation( $middleware ));

        $middleware_group = array_merge( $middleware_group, $middleware );
      }
    }

    return $middleware_group;
  }


}
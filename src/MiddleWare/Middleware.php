<?php

namespace Orcses\PhpLib\Middleware;


use Closure;
use RuntimeException;
use Orcses\PhpLib\Request;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;


abstract class Middleware
{
  protected static $middleware = [
    'auth' => \Orcses\PhpLib\Access\Session::class,
    'auth.api' => \Orcses\PhpLib\Middleware\Auth\Api::class,
  ];


  // Implemented in sub-classes
  abstract public function handle(Request $request, Closure $next);


  public static function get(string $key)
  {
    if( ! array_key_exists($key, static::$middleware)){
      throw new InvalidArgumentException("Middleware '{$key}' does not exist");
    }

    return static::$middleware[ $key ];
  }


  public static function run(Request $request, $group)
  {
    $group = (array) $group;

    $middleware_group = static::buildGroup( $group );

    foreach($middleware_group as [$middleware, $next]){

      $request = call_user_func([$middleware, 'handle'], $request, $next);
    }

    return $request;
  }


  protected static function buildGroup(array $group)
  {
    $middleware_group = [];

    $n = 2;
    while($key = array_pop($group) and $n--){

      $middleware = static::getClass( $key );

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


  protected static function getClass(string $key)
  {
    $class_name = in_array($key, static::$middleware) ? $key : static::get($key);

    $middleware = app()->make( $class_name );

    if( ! $middleware || ! is_a($middleware,static::class)){

      throw new RuntimeException(
        "Middleware class for '{$key}' must extend the base class " . static::class
      );
    }

    return $middleware;
  }


}
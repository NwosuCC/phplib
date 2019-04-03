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


  public static function run(Request $request, array $group)
  {
    $middleware_group = [];

    foreach ((array) $group as $key){
      $middleware = static::getClass( $key );

      if(! $middleware || ! is_a($middleware,static::class)){
        throw new RuntimeException(
          "Middleware class for '{$key}' must extend the base class " . static::class
        );
      }

      $middleware_group[] = static::getClass( $key );
    }

    /*$next = function($request) use($middleware_group){
      $middleware = array_shift($middleware_group);

      return call_user_func([$middleware, 'handle'], $request);
    };*/

    $next = function($request) use($middleware_group){
      return call_user_func([$middleware, 'handle'], $request, $next ?? null);
    };

    $result = null;
    $n = 2;
    while($middleware = array_shift($middleware_group) and $n--){
      $request = call_user_func([$middleware, 'handle'], $request, $next);
    }


    return $result;
  }


  public static function get(string $key)
  {
    if( ! array_key_exists($key, static::$middleware)){
      throw new InvalidArgumentException("Middleware '{$key}' does not exist");
    }

    return static::$middleware[ $key ];
  }


  public static function getClass(string $key)
  {
    $class_name = in_array($key, static::$middleware) ? $key : static::get($key);

    return app()->make( $class_name );
  }


}
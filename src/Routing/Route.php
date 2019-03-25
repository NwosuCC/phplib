<?php

namespace Orcses\PhpLib\Routing;


class Route extends Router
{

  public static function get(string $uri, $target, $parameters = []){
    static::router()->register('get', $uri, $target, $parameters );
  }


  public static function post(string $uri, $target, $parameters = []){
    static::router()->register('post', $uri, $target, $parameters );
  }


  public static function put(string $uri, $target, $parameters = []){
    static::router()->register('put', $uri, $target, $parameters );
  }


  public static function patch(string $uri, $target, $parameters = []){
    static::router()->register('patch', $uri, $target, $parameters );
  }


  public static function delete(string $uri, $target, $parameters = []){
    static::router()->register('delete', $uri, $target, $parameters );
  }


  public static function options(string $uri){
    static::router()->register('options', $uri, '');
  }



}
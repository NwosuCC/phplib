<?php

namespace Orcses\PhpLib\Routing;


class Route extends Router
{

  public static function routeInstance(){
    return new static();
  }


  public function name(string $name){
    $this->setName( $name );

    return $this;
  }


  public static function add(string $method, string $uri, $target, $parameters = []){
    $route = static::routeInstance();

    $route->key = $route->register($method, $uri, $target, $parameters );

    return $route;
  }


  public static function get(string $uri, $target, $parameters = []){
    return static::add('get', $uri, $target, $parameters );
  }


  public static function post(string $uri, $target, $parameters = []){
    return static::add('post', $uri, $target, $parameters );
  }


  public static function put(string $uri, $target, $parameters = []){
    return static::add('put', $uri, $target, $parameters );
  }


  public static function patch(string $uri, $target, $parameters = []){
    return static::add('patch', $uri, $target, $parameters );
  }


  public static function delete(string $uri, $target, $parameters = []){
    return static::add('delete', $uri, $target, $parameters );
  }


  public static function options(string $uri){
    return static::add('options', $uri, '');
  }



}
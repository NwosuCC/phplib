<?php

namespace Orcses\PhpLib\Routing;


class Route extends Router
{

  public static function newInstance()
  {
    return new static();
  }


  public static function group(array $attributes, callable $callback)
  {
    $route = static::newInstance();

    $route->addAttributes($attributes);

    $route->addGroup( $callback );

    return $route;
  }


  public function middleware($names)
  {
    $this->addAttributes(['middleware' => $names]);

    return $this;
  }


  public function namespace(string $name)
  {
    $this->addAttributes(['namespace' => $name]);

    return $this;
  }


  public function prefix(string $name)
  {
    $this->addAttributes(['prefix' => $name]);

    return $this;
  }


  public function name(string $name){
    $this->addAttributes(['name' => $name]);

    return $this;
  }


  public static function add(string $method, string $uri, $target, $parameters = []){
    $route = static::newInstance();

    $route->register( $method, $uri, $target, $parameters );

    return $route;
  }


  public static function get(string $uri, $target, $parameters = [])
  {
    return static::add('get', $uri, $target, $parameters );
  }


  public static function post(string $uri, $target, $parameters = [])
  {
    return static::add('post', $uri, $target, $parameters );
  }


  public static function put(string $uri, $target, $parameters = [])
  {
    return static::add('put', $uri, $target, $parameters );
  }


  public static function patch(string $uri, $target, $parameters = [])
  {
    return static::add('patch', $uri, $target, $parameters );
  }


  public static function delete(string $uri, $target, $parameters = [])
  {
    return static::add('delete', $uri, $target, $parameters );
  }


  public static function options(string $uri)
  {
    return static::add('options', $uri, '');
  }



}
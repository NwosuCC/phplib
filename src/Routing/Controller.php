<?php

namespace Orcses\PhpLib\Routing;


class Controller
{
  // ToDo: replace all app() with properly injected $app instance
  public function __construct()
  {
  }


  /*public function get($op)
  {
    if(array_key_exists($op, $routes = Route::routes())){
      return $routes[ $op ];
    }

    throw new Exception("Operation with key '$op' does not exist");
  }*/


  /*public function getFromRoute(string $name)
  {
    if(array_key_exists($name, $route_names = Route::names())){

      return static::get( $route_names[ $name ] );
    }

    throw new Exception("Route with name '$name' does not exist");
  }*/


  public function getClassAndMethod(string $controller)
  {
    return explode('@', $controller, 2);
  }


  /* ToDo: refactor this doc
   * @param string $controller The controller class name
   *@return \Orcses\PhpLib\Routing\Controller
   */
  public function makeInstanceFor($controller)
  {
    $name_spaced_class = app()->getNamespace('Controllers') . $controller;

    return app()->make( $name_spaced_class );
  }


  /*public function getControllerInstanceFromOp($op)
  {
    return $this->makeInstance( $this->get( $op ) );
  }*/


}
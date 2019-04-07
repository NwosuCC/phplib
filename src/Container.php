<?php

namespace Orcses\PhpLib;


use Closure;
use Exception;
use ReflectionClass;

class Container
{
  /** @var array */
  protected $instances = [];

  protected $resolved = [];


  /**
   * @param      $abstract
   * @param null $concrete
   */
  public function set($abstract, $concrete = NULL)
  {
    if ($concrete === NULL) {
      $concrete = $abstract;
    }

    $this->instances[ $abstract ] = $concrete;
//    pr(['usr' => __FUNCTION__, '000 $concrete' => $concrete, '$abstract' => $abstract, 'instances' => $this->instances]);
  }


  /**
   * Returns the concrete of the supplied abstract
   * @param       $abstract
   * @return mixed
   */
  public function get($abstract)
  {
//    pr(['usr' => __FUNCTION__, '000 $abstract' => $abstract, 'exists' => array_key_exists($abstract, $this->instances)]);
    if ( ! array_key_exists($abstract, $this->instances)) {

      $this->set($abstract);
    }

    return $this->instances[ $abstract ];
  }


  /**
   * Returns a resolved instance
   * @param       $abstract
   * @param array $parameters
   * @return mixed
   */
  public function make($abstract, $parameters = [])
  {
    if ( ! array_key_exists($abstract, $this->resolved)) {

      if($concrete = $this->resolve( $this->get($abstract), $parameters )){

        $this->resolved[ $abstract ] = $concrete;
      }

      $this->set($abstract, $concrete);
    }
//    pr(['usr' => __FUNCTION__, '000 $abstract' => $abstract, '$concrete' => get_class($this->resolved[ $abstract ])]);

    return $this->resolved[ $abstract ];
  }


  /**
   * Pins a resolved concrete instance to the abstract
   * @param       $abstract
   * @param       $concrete
   * @return mixed
   */
  public function pinResolved($abstract, $concrete)
  {
    $this->resolved[ $abstract ] = $concrete;

    return $this->resolved[ $abstract ];
  }


  /**
   * Resolves a single class
   *
   * @param $concrete
   * @param $parameters
   *
   * @return mixed|object
   * @throws Exception
   */
  protected function resolve($concrete, $parameters)
  {
//    pr(['usr' => __FUNCTION__, '000 $concrete' => $concrete, '$parameters' => $parameters]);
    if ($concrete instanceof Closure) {
//      pr(['usr' => __FUNCTION__, 'is Closure $concrete' => $concrete]);
      return $concrete($this, $parameters);
    }

    $reflector = new ReflectionClass($concrete);
//    pr(['usr' => __FUNCTION__, '$reflector' => $reflector, 'isInstantiable' => $reflector->isInstantiable()]);

    // check if class is instantiable
    if ( ! $reflector->isInstantiable()) {
      throw new Exception("Class {$concrete} is not instantiable");
    }

    // get class constructor
    $constructor = $reflector->getConstructor();
//    pr(['usr' => __FUNCTION__, '$constructor' => $constructor]);

    if (is_null($constructor)) {
      // get new instance from class
      return $reflector->newInstance();
    }

    // get constructor params
    $parameters = $constructor->getParameters();
//    pr(['usr' => __FUNCTION__, '$parameters' => $parameters]);

    $dependencies = $this->getDependencies($parameters);
//    pr(['$dependencies', $dependencies]);
//    pr(['usr' => __FUNCTION__, '$dependencies' => $dependencies]);

    // get new instance with dependencies resolved
    return $reflector->newInstanceArgs($dependencies);
  }


  /**
   * resolve single method
   *
   * @param $concrete
   * @param $method
   *
   * @return mixed|object
   * @throws Exception
   */
//  public function resolveMethod($concrete, $method, array $arguments = [])
  public function resolveMethod($concrete, $method)
  {
    $reflector = new \ReflectionMethod($concrete, $method);

//    $method_name = $reflector->getShortName();
//    $method_closure = $reflector->getClosure($concrete);
//    pr(['$method name', $method_name, '$method_closure', $method_closure]);

    $parameters = $reflector->getParameters();
//    pr(['$method $parameters', $parameters]);

    $dependencies = $this->getDependencies($parameters);
//    pr(['$method $dependencies', $dependencies, 'instances', $this->instances]);

    // get new instance with dependencies resolved
//    return $reflector->invokeArgs($concrete, $dependencies);
    return [$reflector, $dependencies];
  }


  /**
   * Get all dependencies resolved
   *
   * @param $parameters
   *
   * @return array
   * @throws Exception
   */
//  public function getDependencies($parameters, array $arguments = [])
  public function getDependencies($parameters)
  {
    $dependencies = [];
//    pr(['getDependencies', $parameters]);

    foreach ($parameters as $parameter) {
      /** @var \ReflectionParameter $parameter */

      // get the type hinted class
//      pr(['$parameter', $parameter]);
      $dependency = $parameter->getClass();

//      pr(['$parameter', $parameter, '$dependency', $dependency,
//        'isDefaultValueAvailable', $def = $parameter->isDefaultValueAvailable(),
//        'getDefaultValue', ($def ? $parameter->getDefaultValue() : 'no-def'),
////        'isDefaultValueConstant', $con = $parameter->isDefaultValueConstant(),
////        'getDefaultValueConstantName', ($con ? $parameter->getDefaultValueConstantName() : 'no-con')
//      ]);

//      pr(['usr' => __FUNCTION__, '$parameter' => $parameter, '$dependency' => $dependency, '$arguments' => $arguments]);
//      pr(['usr' => __FUNCTION__, 'canBePassedByValue' => $parameter->canBePassedByValue(), 'isDefaultValueAvailable' => $parameter->isDefaultValueAvailable()]);


      if ($dependency === NULL) {

        // check if default value for a parameter is available
        /*if (array_key_exists( $name = $parameter->getName(), $arguments)) {

          // Add the value for the parameter from the item in the supplied $arguments
          $dependencies[] = $arguments[ $name ];
        }
        else*/if ($parameter->isDefaultValueAvailable()) {

          // get default value of parameter
          $dependencies[] = $parameter->getDefaultValue();
        }
        else {
          throw new Exception("Can not resolve class dependency {$parameter->name}");
        }
      }
      else {
        // get dependency resolved
        $dependencies[ $parameter->getName() ] = $this->make($dependency->name);
      }
    }

    return $dependencies;
  }
}
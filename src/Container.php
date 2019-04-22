<?php

namespace Orcses\PhpLib;


use Closure;
use ReflectionClass;
use ReflectionException;
use Orcses\PhpLib\Exceptions\Base\BuildException;


class Container
{
  /** @var array */
  protected $instances = [];

  protected $shared = [], $resolved = [];

  protected $error;


  /**
   * @param      $abstract
   * @param null $concrete
   * @param bool $shared
   */
  public function set($abstract, $concrete = null, bool $shared = false)
  {
    if (is_null( $concrete )) {
      $concrete = $abstract;
    }

    $this->instances[ $abstract ] = $concrete;

    if($shared){
      $this->shared[ $abstract ] = $concrete;
    }
  }


  /**
   * Returns the concrete of the supplied abstract
   * @param      $abstract
   * @return mixed
   */
  public function get($abstract)
  {
    if ( ! array_key_exists($abstract, $this->instances)) {

      $this->set( $abstract );
    }

    return $this->instances[ $abstract ] ?? null;
  }


  /**
   * Returns a resolved instance
   * @param       $abstract
   * @param array $arguments
   * @return mixed
   */
  public function make($abstract, $arguments = [])
  {
    pr(['usr' => __FUNCTION__, '000 $abstract' => $abstract, 'shared' => array_key_exists($abstract, $this->shared)]);

    $concrete = $this->get($abstract);

    if ( ! array_key_exists($abstract, $this->shared)) {

      // Not singleton; return a new resolved instance
      return $this->resolve( $concrete, $arguments );
    }

    pr(['usr' => __FUNCTION__, '111 $abstract' => $abstract,'shared' => true, 'resolved' => array_key_exists($abstract, $this->resolved)]);

    // Is singleton
    if ( ! array_key_exists($abstract, $this->resolved)) {
      // No previously resolved instance for this singleton abstract

      if (is_string($concrete) && array_key_exists($concrete, $this->resolved)) {

        // Return the previously resolved concrete instance
        $this->resolved[ $abstract ] = $this->resolved[ $concrete ];
      }
      elseif($concrete = $this->resolve( $concrete, $arguments )){

        // Resolve and return a new concrete instance
        $this->resolved[ $abstract ] = $concrete;
      }
    }
    pr(['usr' => __FUNCTION__, '222 $abstract' => $abstract,'shared' => true, 'resolved' => true, '$concrete' => get_class($this->resolved[ $abstract ])]);

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
//    pr(['usr' => __FUNCTION__, '$abstract' => $abstract, '$concrete' => $this->resolved[ $abstract ], 'new $concrete' => $concrete->input()]);

    return $this->resolved[ $abstract ];
  }


  /**
   * @return string
   */
  public function getError()
  {
    return $this->error;
  }


  /**
   * Resolves a single class
   *
   * @param $concrete
   * @param $arguments
   *
   * @return mixed|object
   * @throws BuildException
   */
  protected function resolve($concrete, $arguments)
  {
    pr(['usr' => __FUNCTION__, '$concrete' => $concrete, 'class exists' => is_object($concrete) or class_exists($concrete), '$arguments' => $arguments]);
    if ($concrete instanceof Closure) {
//      pr(['usr' => __FUNCTION__, 'is Closure $concrete' => $concrete]);
      return $concrete( $this, $arguments );
    }

    try {
      $reflector = new ReflectionClass( $concrete );
//    pr(['usr' => __FUNCTION__, '$reflector' => $reflector, 'isInstantiable' => $reflector->isInstantiable()]);
    }
    catch (ReflectionException $e){
//      pr(['usr' => __FUNCTION__, '$e' => $e->getMessage()]);

      throw new BuildException( $e->getMessage() );
    }

    // check if class is instantiable
    if ( ! $reflector->isInstantiable()) {

      $this->error = "Class {$concrete} is not instantiable";
//      pr(['usr' => __FUNCTION__, 'error 111' => $this->error]);

      throw new BuildException( $this->error );
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

    $dependencies = $this->getDependencies($parameters, $arguments);
//    pr(['$dependencies', $dependencies]);
//    pr(['usr' => __FUNCTION__, '$dependencies' => $dependencies]);

    // get new instance with dependencies resolved
    return $reflector->newInstanceArgs($dependencies);
  }


  /**
   * Resolves a class method
   *
   * @param $concrete
   * @param $method
   *
   * @return mixed|object
   * @throws BuildException
   */
//  public function resolveMethod($concrete, $method, array $arguments = [])
  public function resolveMethod($concrete, $method)
  {
    try {
      $reflector = new \ReflectionMethod($concrete, $method);
    }
    catch (ReflectionException $e){

      throw new BuildException( $e->getMessage() );
    }
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
   * @param array $arguments
   *
   * @return array
   * @throws BuildException
   */
  public function getDependencies($parameters, array $arguments = null)
  {
    $dependencies = [];

    $arguments = $arguments ?: [];

//    pr(['getDependencies', $parameters]);

    foreach ($parameters as $parameter) {
      /** @var \ReflectionParameter $parameter */

      $parameter_name = $parameter->getName();

      // get the type hinted class
//      pr(['$parameter', $parameter]);

//      pr(['$parameter', $parameter, '$dependency', $dependency,
//        'isDefaultValueAvailable', $def = $parameter->isDefaultValueAvailable(),
//        'getDefaultValue', ($def ? $parameter->getDefaultValue() : 'no-def'),
////        'isDefaultValueConstant', $con = $parameter->isDefaultValueConstant(),
////        'getDefaultValueConstantName', ($con ? $parameter->getDefaultValueConstantName() : 'no-con')
//      ]);

//      pr(['usr' => __FUNCTION__, '$parameter' => $parameter, '$dependency' => $dependency, '$arguments' => $arguments]);
//      pr(['usr' => __FUNCTION__, 'canBePassedByValue' => $parameter->canBePassedByValue(), 'isDefaultValueAvailable' => $parameter->isDefaultValueAvailable()]);


      // First, check if parameter value is supplied in the $arguments
      // This lets the developer inject known dynamic dependencies which cannot easily be type-hinted
      // E.g injecting different (dynamic) Model instances (User, Post, etc) into a HasMany::class
      if (array_key_exists( $parameter_name, $arguments)) {

        // Get the value for the parameter from the supplied $arguments
        $dependency = $arguments[ $parameter_name ];

        // Build the dependency if it is a class name and the class exists
        if(is_string($dependency) && class_exists($dependency)){

          $dependency = $this->make( $dependency );
        }

        $dependencies[ $parameter_name ] = $dependency;
      }
      elseif (is_null( $dependency = $parameter->getClass() )){

        // Use the parameter default value, if available
        if ($parameter->isDefaultValueAvailable()) {

          $dependencies[ $parameter_name ] = $parameter->getDefaultValue();
        }
        else {
          throw new BuildException("Could not resolve class dependency {$parameter->name}");
        }
      }
      else {
        // Get resolved dependency
        $dependencies[ $parameter_name ] = $this->make($dependency->name);
      }
    }

    return $dependencies;
  }
}
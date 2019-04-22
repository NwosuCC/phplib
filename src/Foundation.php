<?php

namespace Orcses\PhpLib;

use Exception;
use Dotenv\Dotenv;
use Orcses\PhpLib\Exceptions\Base\FileNotFoundException;


class Foundation
{
  protected $container;

  protected $error;

  protected $providers = [];

  protected $facades = [];

  protected $booted = false;

  protected $start_time;

  protected $base_path, $config;


  protected function __construct($base_dir)
  {
    $this->container = new Container();

    $this->boot($base_dir);
  }


  protected final function boot(string $base_directory)
  {
    if( ! $this->booted){
      define(
        'ORCSES_START',
        $this->start_time = microtime(true)
      );

      $this->booted = true;

      $this->base_path = $base_directory;

      $this->importEnvironmentVariables();

      $this->loadConfigurations();

      // ToDo: implement ServiceProvider->boot()
      $this->registerServiceProviders();

      $this->initializeBaseClasses();

      $this->bootServiceProviders();
    }
  }


  public function baseDir()
  {
    return $this->base_path;
  }


  public function appDir()
  {
    return $this->baseDir() . '/app';
  }


  protected function importEnvironmentVariables()
  {
    /*
     * Load the Environment Variables
     */
    $dot_env = Dotenv::create(static::baseDir() . '/');

    //$dot_env->overload(); // overwrite any existing ENV Variables
    $dot_env->load();

    /*
     * Apply constraints if necessary. Specify env keys that MUST exist in the .env file
     */
    $dot_env->required([
      'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'
    ]);

  }


  protected function loadConfigurations()
  {
    try {
      $config_dir = $this->baseDir() . '/config/';

      $file_path = $config_dir . 'app.php';

      if(file_exists($file_path)){

        $this->config['app'] = require (''.$file_path.'');

        $this->config['app']['dir'] = $this->appDir();

        // Load other config
        foreach ($this->config['app']['files']['config'] as $file){

          $file_path = $config_dir . "{$file}.php";

          if(file_exists($file_path)){

            $this->config[ $file ] = require (''.$file_path.'');
          }
        }
      }
    }
    catch (Exception $e){
      throw new FileNotFoundException("Configuration", $file_path ?? '');
    }
  }


  protected function registerServiceProviders()
  {
    try {
      if($providers = require ( $this->baseDir() . '/config/providers.php'.'' )){

        foreach ($providers as $class_name){

          $this->singleton( $class_name );

          $provider_class = $this->make( $class_name );

          $provider_class->register();

          $this->providers[ $class_name ] = $provider_class;
        }
      }
    }
    catch (Exception $e){
      $this->error = $e->getMessage();
    }
  }


  protected function bootServiceProviders()
  {
    foreach ($this->providers as $provider_class){

      $provider_class->boot();
    }
  }


  public function pin($abstract, $concrete)
  {
    $this->container->pinResolved( $abstract, $concrete );
  }


  public function bind($abstract, $concrete = null)
  {
    $this->container->set( $abstract, $concrete );
  }


  public function singleton($abstract, $concrete = null)
  {
    $this->container->set( $abstract, $concrete, true );
  }


  public function make($abstract)
  {
    return $this->build( $abstract, [] );
  }


  /**
   * Builds an instance of the supplied class name using the supplied arguments
   * @param $class_name
   * @param array $arguments
   * @return mixed
   */
  public function build(string $class_name, array $arguments)
  {
    return $this->container->make( $class_name, $arguments );
  }


  /**
   * @return mixed
   */
  public function getError()
  {
    return $this->error;
  }


  protected function initializeBaseClasses()
  {
    foreach ($this->aliases() as $facade_accessor => $class_name){

      $this->facades[ $facade_accessor ] = $this->make( $class_name );

      $this->singleton( $class_name, $this->facades[ $facade_accessor ] );

      $this->singleton( $facade_accessor, $class_name );
    }
  }


  /**
   * Returns the actual class name for the specified $key
   * @param $key
   * @return string
   */
  protected function getAlias(string $key)
  {
    return $this->aliases()[ $key ] ?? null;
  }


  /**
   * The keys are the facade accessor names
   */
  protected function aliases()
  {
    return [
      'Router' => \Orcses\PhpLib\Routing\Router::class,
      'Response' => \Orcses\PhpLib\Response::class,
      'Validator' => \Orcses\PhpLib\Validator::class,
      'Controller' => \Orcses\PhpLib\Routing\Controller::class,
      'Authenticatable' => \Orcses\PhpLib\Interfaces\Auth\Authenticatable::class,
      'Auth' => \Orcses\PhpLib\Access\Auth::class,
      'Request' => \Orcses\PhpLib\Request::class,
    ];
  }



}

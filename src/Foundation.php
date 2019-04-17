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

  protected $base_path, $config, $dot_config;


  protected function __construct($base_dir)
  {
    $this->container = new Container();

    $this->boot($base_dir);
  }


  private function boot(string $base_directory){
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
    }
  }


  public function baseDir(){
    return $this->base_path;
  }


  public function appDir(){
    return $this->baseDir() . '/app';
  }


  protected function importEnvironmentVariables(){
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
      $file_path = $this->baseDir() . '/config/app.php';

      if(file_exists($file_path)){

        $this->config = require (''.$file_path.'');

        $this->config['app']['dir'] = $this->appDir();

//        $this->dot_config = Arr::toDotNotation( $this->config );
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

          $provider_class = $this->make( $class_name );

          $provider_class->register();

          $this->providers[ $class_name ] = $provider_class;
        }
      }
    }
    catch (Exception $e){}
  }


  public function pin($abstract, $concrete)
  {
    $this->container->pinResolved( $abstract, $concrete );
  }


  public function bind($abstract, $concrete)
  {
    $this->container->set( $abstract, $concrete );
  }


  public function make($class_name)
  {
    if(array_key_exists($class_name, $this->facades)){
      return $this->facades[ $class_name ];
    }

    return $this->build( $class_name );
  }


  // ToDo: create and use facades (like in App::make()) instead of full class namespaces
  public function build(string $class_name)
  {
    return $this->container->make( $class_name );
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
    foreach ($this->aliases() as $facade_accessor => $class){

      if( ! array_key_exists($facade_accessor, $this->facades)){

        $this->facades[ $facade_accessor ] = $this->build($class);
      }
    }
  }


  /**
   * Returns the actual class name for the specified $key
   * @param $key
   * @return string
   */
  protected function getAlias(string $key){
    return $this->aliases()[ $key ] ?? null;
  }


  /**
   * The keys are the facade accessor names
   */
  protected function aliases(){
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

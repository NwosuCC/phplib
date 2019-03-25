<?php

namespace Orcses\PhpLib;

use Error;
use Exception;
use Dotenv\Dotenv;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Exceptions\ClassNotFoundException;
use Orcses\PhpLib\Exceptions\ConfigurationFileNotFoundException;


class Foundation
{
  protected $container, $instances = [], $facades = [];

  protected $booted = false;

  protected $start_time;

  protected $base_path, $config;


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
      $config = require ( $this->baseDir() . '/config/app.php'.'' );

      $this->config = Arr::toDotNotation($config);

      $this->config['app.dir'] = $this->appDir();
    }
    catch (Exception $e){
      throw new ConfigurationFileNotFoundException();
    }
  }


  public function make(string $class_name){
    if(array_key_exists($class_name, $this->facades)){
      return $this->facades[ $class_name ];
    }

    return $this->build( $class_name );
  }


  // ToDo: create and use facades (like in App::make()) instead of full class namespaces
  public function build(string $class_name)
  {
    if( ! array_key_exists($class_name, $this->instances)){
      try {
        $class_instance = $this->container->get( $class_name );
        pr(['build', $class_instance]);

        $this->instances[ $class_name ] = $class_instance;
      }
      // ToDo: remove the dd() in catch(){} block
      catch (Error $e){
        dd('build Error', $e->getTrace());
      }
      catch (Exception $e){
        dd('build Exception', $e->getMessage());
      }

      if( ! empty($e)){
        throw new ClassNotFoundException( $class_name );
      }
    }

    return $this->instances[ $class_name ];
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
      'Controller' => \Orcses\PhpLib\Routing\Controller::class,
    ];
  }



}

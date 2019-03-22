<?php

namespace Orcses\PhpLib;

use Dotenv\Dotenv;

class Foundation {

  protected static $booted = false;

  protected static $start_time;

  protected static $base_directory = '';


  protected function __construct($base_dir)
  {
    $this->boot($base_dir);
  }


  private function boot(string $base_directory){
    if( ! static::$booted){
      static::$booted = true;

      define(
        'ORCSES_START',
        static::$start_time = microtime(true)
      );

      static::$base_directory = $base_directory;

      static::importEnvironmentVariables();
    }
  }


  protected function appRoot(){
    return self::$base_directory;
  }


  private static function importEnvironmentVariables(){
    /*
     * Load the Environment Variables
     */
//    $dot_env = Dotenv::create(static::$base_directory . '/../');
    $dot_env = Dotenv::create(static::$base_directory . '/');

    //$dot_env->overload(); // overwrite any existing ENV Variables
    $dot_env->load();

    /*
     * Apply constraints if necessary. Specify env keys that MUST exist in the .env file
     */
    $dot_env->required([
      'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'
    ]);

  }

}

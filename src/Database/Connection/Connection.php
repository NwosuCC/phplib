<?php

namespace Orcses\PhpLib\Database\Connection;


use Orcses\PhpLib\Exceptions\Database\ConnectionNotFoundException;
use Orcses\PhpLib\Interfaces\Connectible;


abstract class Connection implements Connectible
{
  protected $default;

  protected $config = [];

  protected $connections = [];

  /** The current database connected to */
  protected $database;


  public function __construct($driver = '')
  {
    $this->initialize($driver);
  }


  // Child classes can't override this method because it is final
  final protected function initialize($driver)
  {
    if( ! app()->config($driver)){
      $driver = $this->getDefaultConnection();
    }

    if(! $this->config = app()->config("database.drivers.{$driver}")){
      throw new ConnectionNotFoundException('');
    }

    $this->setDatabase($this->config['database']);
  }


  public function getDefaultConnection()
  {
    if( ! $this->default){
      $this->default = app()->config("database.default");
    }

    return $this->default;
  }


  /**
   * Called during initialize(), this sets the database this connection is meant for
   * @param string $database
   */
  public function setDatabase(string $database){
    $this->database = $database;
  }


  public function getDatabase(){
    return $this->database;
  }


}
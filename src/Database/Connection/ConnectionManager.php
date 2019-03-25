<?php

namespace Orcses\PhpLib\Database\Connection;


use Orcses\PhpLib\Exceptions\Database\DatabaseConnectionNotFoundException;

class ConnectionManager
{
  protected $driver;

  protected $connections = [];


  public function __construct()
  {
  }


  public function addConnection($name, $connection){
    $this->connections[$name] = $connection;
  }


  public function hasConnection($name){
    return array_key_exists($name, $this->connections);
  }


  public function getConnection($name){
    if( ! $this->hasConnection($name)){
      throw new DatabaseConnectionNotFoundException($name);
    }

    return $this->connections[$name];
  }


  public function removeConnection($name){
    if($this->hasConnection($name)){
      unset($this->connections[ $name ]);
    }
  }



}
<?php

namespace Orcses\PhpLib\Database\Connection;


class Connector
{
  protected $driver;


  public function __construct($driver = '')
  {
    $this->driver = $driver ?: config('database.driver');
  }


  public function connect()
  {
  }


  public function close($connection)
  {
  }

}
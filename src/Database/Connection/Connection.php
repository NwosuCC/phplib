<?php

namespace Orcses\PhpLib\Database\Connection;


use Orcses\PhpLib\Interfaces\Connectible;


abstract class Connection implements Connectible
{
  protected $driver;

  protected $config;

  protected $connections;


  public function __construct($driver = '')
  {
    $this->driver = $driver ?: config('database.driver');

    $this->initialize();
  }

  // Child classes can't override this method because it is final
  final protected function initialize(){

    $this->config = config("database.{$this->driver}");

  }

}
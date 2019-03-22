<?php

namespace Orcses\PhpLib\Database\Connection;


use Memcache;

class MemcacheConnection extends Connection
{

  public function __construct()
  {
    parent::__construct('memcache');
  }


  public function connect()
  {
    $host = $port = $timeout = null;

    extract($this->config);

    if($connection = new Memcache($host, $port ?: 11211, $timeout)){
      // ...

    }

    return $connection;
  }


  public function close($connection)
  {
    $connection->close();
  }


}
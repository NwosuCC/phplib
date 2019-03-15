<?php

namespace Orcses\PhpLib\Database\Connection;

use mysqli as MySQLi;
use Orcses\PhpLib\Interfaces\Connectible;


class MysqlConnector extends Connector implements Connectible
{
  protected $driver;


  public function __construct()
  {
    parent::__construct('mysql');
  }


  /**
   * @return MySQLi instance
   */
  public function connect()
  {
    $credentials = config("database.{$this->driver}");

    $host = $username = $password = $database = '';

    extract($credentials);

    if($connection = new MySQLi($host, $username, $password, $database)){
      $connection->query("use {$database}");

      $this->configureTimezone($connection, []);
    }

    return $connection;
  }


  public function close($connection)
  {
    $connection->close();
  }


  protected function configureTimezone(MySQLi $connection, array $config)
  {
    if (isset($config['timezone'])) {
      $timezone = $config['timezone'];
      $connection->query("set time_zone = '{$timezone}'");
    }
  }

}
<?php

namespace Orcses\PhpLib\Database\Connection;

use mysqli as MySQLi;


class MysqlConnection extends Connection
{

  public function __construct()
  {
    parent::__construct('mysql');
  }


  /**
   * @return MySQLi instance
   */
  public function connect()
  {
    $host = $username = $password = $database = '';

    extract($this->config);

    if($connection = new MySQLi($host, $username, $password, $database)){

      $connection->set_charset("utf8mb4_unicode_ci");

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
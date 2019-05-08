<?php

namespace Orcses\PhpLib\Database\Connection;

use PDO;
use PDOException;


class PDOConnection extends Connection
{

  public function __construct()
  {
    parent::__construct('mysql');
  }


  /**
   * @return PDO instance
   */
  public function connect()
  {
    $host = $username = $password = $database = $char_set = '';

    extract($this->config);

    $dsn = "mysql:host={$host};dbname={$database}";

    $dsn .= $char_set ? ";charset={$char_set}" : '';

    $options = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false
    ];

    try {
      $connection = new PDO($dsn, $username, $password, $options);

      $connection->query("use {$database}");
    }
    catch(PDOException $exception){
      throw new PDOException( $exception->getMessage(), (int) $exception->getCode() );
    }

    return $connection;
  }


  public function close($connection)
  {
    $connection->close();
  }

}
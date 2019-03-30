<?php

namespace Orcses\PhpLib\Interfaces;


interface Connectible
{
  /**
   * Creates and returns the connection instance
   * @return array [$connection, $database]
   */
  public function connect();


  /**
   * The default connection name
   * @return $this
   */
  public function getDefaultConnection();


  /**
   * Sets the database this connection is meant for
   * @param string $database
   */
  public function setDatabase(string $database);


  /**
   * Returns the database connected to
   */
  public function getDatabase();


  /**
   * Closes the connection
   * @param mixed $connection   The connection instance to close
   */
  public function close($connection);

}
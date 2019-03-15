<?php

namespace Orcses\PhpLib\Interfaces;


interface Connectible
{
  /**
   * The connection instance
   * @return $this
   */
  public function connect();


  /**
   * Closes the connection
   * @param mixed $connection   The connection instance to close
   */
  public function close($connection);

}
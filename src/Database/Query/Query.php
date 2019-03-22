<?php

namespace Orcses\PhpLib\Database\Query;


use Orcses\PhpLib\Interfaces\Connectible;


class Query
{
  protected $connection;

  public function __construct(Connectible $connection) {

    $this->connection = $connection->connect();

  }


}
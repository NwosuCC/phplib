<?php

namespace Orcses\PhpLib\Database\Schema;


use Orcses\PhpLib\Database\Schema\Columns\Column;

class Index
{
  protected $name;

  protected $columns = [];

  protected $type;


  /**
   * @param string    $type
   * @param Column[]  $columns
   */
  public function __construct(string $type, array $columns)
  {
    $this->type = $type;

    $this->columns = $columns;
  }


  public function unique(string $type, Column $column)
  {

    $this->columns[ $type ][] = $column;
  }


}
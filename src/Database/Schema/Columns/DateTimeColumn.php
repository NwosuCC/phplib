<?php

namespace Orcses\PhpLib\Database\Schema\Columns;


class DateTimeColumn extends Column
{
  protected $on_update;

  // Merged in at parent construct()
  protected $props = [
    'on_update'
  ];


  public function setOnUpdate(string $on_update) // Use Command instead
  {
    // Only if DateTime OR Timestamp

    $this->on_update = $on_update;

    return $this;
  }


  public function getUpdate()
  {
    return $this->on_update;
  }


}
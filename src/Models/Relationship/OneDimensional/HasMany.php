<?php

namespace Orcses\PhpLib\Models\Relationship\OneDimensional;


use Orcses\PhpLib\Models\Model;


class HasMany extends HasOne
{

  /** @return Model */
  public function model()
  {
    return $this->relationsQuery( $this->owned, $this->owner );
  }


  public function get()
  {
    return $this->model()->get();
  }


  public function find(string  $id)
  {
    return $this->model()->find( $id );
  }


  public function first()
  {
    return $this->model()->first();
  }


}
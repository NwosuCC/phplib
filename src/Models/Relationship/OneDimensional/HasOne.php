<?php

namespace Orcses\PhpLib\Models\Relationship\OneDimensional;


use Orcses\PhpLib\Models\Model;


class HasOne extends OneDimRelationship
{

  public function __construct(Model $parent, Model $related)
  {
    $this->owner = $parent;

    $this->owned = $related;
  }


  /**
   * Since there can be only one owned object in this relationship, it is more intuitive
   * to name the method 'get()' instead of 'first()'. Using 'first()' will give the
   * undesired impression that there are more objects
   *
   * @return Model
   */
  public function model()
  {
    return $this->relationsQuery( $this->owned, $this->owner )

      ->limit(1)

      ->first();
  }


}
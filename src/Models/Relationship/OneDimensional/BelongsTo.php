<?php

namespace Orcses\PhpLib\Models\Relationship\OneDimensional;


use Orcses\PhpLib\Models\Model;


class BelongsTo extends OneDimRelationship
{

  public function __construct(Model $related, Model $parent)
  {
    $this->owned = $related;

    $this->owner = $parent;
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
    return $this->relationsQuery( $this->owner, $this->owned )

      ->limit(1)

      ->first();
  }


}
<?php

namespace Orcses\PhpLib\Models\Relationship\MultiDimensional;


use Orcses\PhpLib\Models\Model;


class MorphsToOne extends MultiDimRelationship
{

  public function __construct(Model $parent, Model $morph)
  {
    $this->parent = $parent;

    $this->morph = $morph;
  }


  /** @return Model */
  public function model()
  {
    return $this->morphsQuery( $this->morph, $this->parent )

      ->limit(1)

      ->first();
  }


}
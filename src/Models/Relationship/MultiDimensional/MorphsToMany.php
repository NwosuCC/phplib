<?php

namespace Orcses\PhpLib\Models\Relationship\MultiDimensional;


use Orcses\PhpLib\Models\Model;
use Orcses\PhpLib\Exceptions\InvalidOperationException;


class MorphsToMany extends MorphsToOne
{

  /** @return Model */
  public function model()
  {
    return $this->morphsQuery( $this->morph, $this->parent );
  }


  public function attach(Model $morph)
  {
    if(($supplied = get_class($morph)) !== ($expected = get_class($this->morph))){

      throw new InvalidOperationException(
        "This morph expects '{$expected}' model but got '{$supplied}' instead"
      );
    }

    return $this->attachMorph( $owned );
  }



}
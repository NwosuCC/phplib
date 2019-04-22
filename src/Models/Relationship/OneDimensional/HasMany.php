<?php

namespace Orcses\PhpLib\Models\Relationship\OneDimensional;


use Orcses\PhpLib\Models\Model;
use Orcses\PhpLib\Exceptions\InvalidOperationException;


class HasMany extends HasOne
{

  /** @return Model */
  public function model()
  {
    return $this->relationsQuery( $this->owned, $this->owner );
  }


  public function save(Model $owned)
  {
    if(($supplied = get_class($owned)) !== ($expected = get_class($this->owned))){

      throw new InvalidOperationException(
        "This relation expects '{$expected}' model but got '{$supplied}' instead"
      );
    }

    return $this->saveOwned( $owned );
  }


}
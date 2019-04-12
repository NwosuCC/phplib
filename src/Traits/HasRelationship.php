<?php

namespace Orcses\PhpLib\Traits;


trait HasRelationship
{

  public function getIdProp()
  {
    $prop = strtolower( basename(static::class ) );

    return $prop . '_id';
  }

  /**
   * @param string $related
   * @return \Orcses\PhpLib\Models\Model
   */
  public function belongsTo(string $related)
  {
//    pr(['usr' => __FUNCTION__, '$prop' => $this->getIdProp(), '$related_class' => $related_class]);

    return app()->make($related)->where([
      $this->getIdProp() => $this->getKey()
    ]);
  }


}
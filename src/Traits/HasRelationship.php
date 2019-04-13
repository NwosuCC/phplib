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
  public function hasMany(string $related)
  {
    pr(['usr' => __FUNCTION__, '$prop' => $this->getIdProp(), 'getKey' => $this->getKey(), '$related' => $related]);
    $aa = app()->make($related)->refresh()->where([
      $this->getIdProp() => $this->getKey()
    ]);

    return $aa;
  }


}
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
  public function hasOne(string $related)
  {
    return app()->make($related)
      ->refreshState()
      ->where([ $this->getIdProp() => $this->getKey() ])
      ->limit(1);
  }


  /**
   * @param string $related
   * @return \Orcses\PhpLib\Models\Model
   */
  public function hasMany(string $related)
  {
    return app()->make($related)
      ->refreshState()
      ->where([ $this->getIdProp() => $this->getKey() ]);
  }


  /**
   * @param string $related
   * @return \Orcses\PhpLib\Models\Model
   */
  public function belongsTo(string $related)
  {
    $aa = app()->make( $related )
      ->refreshState()
      ->where([ $this->getIdProp() => $this->getKey() ])
      ->limit(1);

    pr(['usr' => __FUNCTION__, '$aa' => $aa, 'getIdProp' => $this->getIdProp(), 'getKey' => $this->getKey()]);

    return $aa;
  }



}
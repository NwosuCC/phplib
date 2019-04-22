<?php

namespace Orcses\PhpLib\Traits;


use Orcses\PhpLib\Models\Relationship\OneDimensional\HasOne;
use Orcses\PhpLib\Models\Relationship\OneDimensional\HasMany;
use Orcses\PhpLib\Models\Relationship\OneDimensional\BelongsTo;


trait HasRelationship
{
  /**
   * @param string $related
   * @return \Orcses\PhpLib\Models\Relationship\OneDimensional\HasOne
   */
  public function hasOne(string $related)
  {
    return app()->build( HasOne::class, [
      'parent' => $this,
      'related' => $related
    ]);
  }


  /**
   * @param string $related
   * @return \Orcses\PhpLib\Models\Relationship\OneDimensional\HasMany
   */
  public function hasMany(string $related)
  {
    return app()->build( HasMany::class, [
      'parent' => $this,
      'related' => $related
    ]);
  }


  /**
   * @param string $related
   * @return \Orcses\PhpLib\Models\Relationship\OneDimensional\BelongsTo
   */
  public function belongsTo(string $related)
  {
    return app()->build( BelongsTo::class, [
      'related' => $this,
      'parent' => $related
    ]);
  }


}
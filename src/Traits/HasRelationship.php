<?php

namespace Orcses\PhpLib\Traits;


use Orcses\PhpLib\Models\Relationship\OneDimensional\HasOne;
use Orcses\PhpLib\Models\Relationship\OneDimensional\HasMany;
use Orcses\PhpLib\Models\Relationship\OneDimensional\BelongsTo;
use Orcses\PhpLib\Models\Relationship\MultiDimensional\MorphsTo;
use Orcses\PhpLib\Models\Relationship\MultiDimensional\MorphsToMany;
use Orcses\PhpLib\Models\Relationship\MultiDimensional\MorphsToOne;


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


  /**
   * @param string $morph
   * @return \Orcses\PhpLib\Models\Relationship\MultiDimensional\MorphsToOne
   */
  public function morphsToOne(string $morph)
  {
    return app()->build( MorphsToOne::class, [
      'parent' => $this,
      'morph' => $morph
    ]);
  }


  /**
   * @param string $morph
   * @return \Orcses\PhpLib\Models\Relationship\MultiDimensional\morphsToMany
   */
  public function morphsToMany(string $morph)
  {
    return app()->build( morphsToMany::class, [
      'parent' => $this,
      'morph' => $morph
    ]);
  }


  /**
   * @return \Orcses\PhpLib\Models\Relationship\MultiDimensional\morphsTo
   */
  public function morphsTo()
  {
    return app()->build( morphsTo::class, [
      'morph' => $this,
    ]);
  }


}
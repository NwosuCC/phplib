<?php

namespace Orcses\PhpLib\Models\Relationship\MultiDimensional;


use Orcses\PhpLib\Models\Model;


class MorphsTo extends MultiDimRelationship
{
  protected $parent;

  protected $morph;


  public function __construct(Model $morph)
  {
    $this->morph = $morph;

    $this->parent = $this->getMorph();
  }


  protected function getMorph()
  {

  }


  public function model()
  {
    //
  }


}
<?php

namespace Orcses\PhpLib\Models;

use Orcses\PhpLib\Interfaces\Modelable;
use Orcses\PhpLib\Models\Model;


class PseudoModel extends Model
{

  /**
   * Instructs the model to defer loading of table columns since table name is not set yet
   */
  protected $lazy_load = true;

  protected $pseudo_object;


  public function __construct(Modelable $object)
  {
    $this->pseudo_object = $object;

    $this->setTable( $this->pseudo_object->getTable() );

    parent::__construct();
  }


}
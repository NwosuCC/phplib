<?php

namespace Orcses\PhpLib\Models\Relationship\MultiDimensional;


use Orcses\PhpLib\Models\Model;
use Orcses\PhpLib\Models\Relationship\Relationship;


class MultiDimRelationship extends Relationship
{
  /** @var Model $parent */
  protected $parent;

  /** @var Model $morph */
  protected $morph;


  /**
   *
   * @param Model $owned
   * @return Model
   */
  /**
   * Persists a new instance of the related model in the database
   * First, adds the foreign key to the owned model, then, creates it
   *
   * @param Model $morph   A filled, unsaved instance of the owned class
   * @return Model
   */
  protected function attachMorph(Model $morph)
  {
    $parent_morph_type = $this->getMorphTypeOf( $this->parent );

    $parent_key = $this->parent->getKey();

    // ToDo; add config for the 'morph' prefix: default is 'morph' for 'morph_type' and 'morph_id'
    // One might want to use 'record' for 'record_type' and 'record_id'
    $morph->setAttribute( 'morph_type', $parent_morph_type );
    $morph->setAttribute( 'morph_id', $parent_key );

    return $morph->save();
  }


  protected function detachMorph(Model $morph)
  {
    $parent_morph_type = $this->getMorphTypeOf( $this->parent );

    $parent_key = $this->parent->getKey();

    // ToDo; add config for the 'morph' prefix: default is 'morph' for 'morph_type' and 'morph_id'
    // One might want to use 'record' for 'record_type' and 'record_id'
    $morph->setAttribute( 'morph_type', $parent_morph_type );
    $morph->setAttribute( 'morph_id', $parent_key );

    return $morph->save();
  }


  /**
   * Prepares the query to retrieve a related morph model
   * @param Model $related  The related model to retrieve
   * @param Model $subject  The subject model whose key will be used for the search
   * @return Model
   */
  protected function morphsQuery(Model $related, Model $subject)
  {
    $subject_morph_type = $this->getMorphTypeOf( $subject );

    $subject_key = $subject->getKey();

    // E.g: If User (id = 2) 'morphsTo' Image, calling $user->image()->get() will imply:
    // $related = Image, $subject = User, $where = ['morph_type' => 'user', 'morph_id' => 2]

    pr(['usr' => __FUNCTION__, '$subject_morph_type' => $subject_morph_type, '$subject_key' => $subject_key]);
    return $related->refreshState()

      ->where([
        'morph_type' => $subject_morph_type, 'morph_id' => $subject_key
      ])

      ->orderBy( $related->getKeyName() );
  }


  protected function getMorphTypeOf(Model $model)
  {
    return strtolower( basename( get_class( $model )));
  }


}
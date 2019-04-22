<?php

namespace Orcses\PhpLib\Models\Relationship\OneDimensional;


use Orcses\PhpLib\Models\Model;
use Orcses\PhpLib\Models\Relationship\Relationship;


class OneDimRelationship extends Relationship
{
  /** @var Model $owner */
  protected $owner;

  /** @var Model $owned */
  protected $owned;


  /**
   *
   * @param Model $owned
   * @return Model
   */
  /**
   * Persists a new instance of the related model in the database
   * First, adds the foreign key to the owned model, then, creates it
   *
   * @param Model $owned   A filled, unsaved instance of the owned class
   * @return Model
   */
  protected function saveOwned(Model $owned)
  {
    $owner_relation_key_name = $this->getRelationKeyNameOf( $this->owner );

    $owner_key = $this->owner->getKey();

    $owned->setAttribute( $owner_relation_key_name, $owner_key );

    return $owned->save();
  }


  /**
   * Prepares the query to retrieves a related model
   * @param Model $related  The related model to retrieve
   * @param Model $subject  The subject model whose key will be used for the search
   * @return Model
   */
  protected function relationsQuery(Model $related, Model $subject)
  {
    $subject_relation_key_name = $this->getRelationKeyNameOf( $subject );

    $subject_key = $subject->getKey();

    $where = [ $subject_relation_key_name => $subject_key ];

    // E.g: If User (id = 2) 'hasOne' Post, calling $post->user()->get() will imply:
    // $related = Post, $subject = User, $where = ['user_id' => 2]

    return $related->refreshState()

      ->where($where)

      ->orderBy( $related->getKeyName() );
  }


  protected function getRelationKeyNameOf(Model $model)
  {
    $prop = strtolower( basename( get_class( $model )));

    return $prop . '_' . $model->getKeyName();
  }


}
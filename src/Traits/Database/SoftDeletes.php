<?php

namespace Orcses\PhpLib\Traits\Database;


trait SoftDeletes
{

  protected static $_DELETED_AT = 'deleted_at';

  protected  $with_deleted = false;


  protected function canBeDeleted()
  {
    pr(['usr' => __FUNCTION__, '$_DELETED_AT' => static::$_DELETED_AT, 'cols' => $this->{'table_columns'}]);

    $table_columns = $this->softDeletesSafeCall('getTableColumns');

    return array_key_exists( static::$_DELETED_AT, $table_columns );
  }


  public function withDeleted()
  {
    $this->with_deleted = true;

    return $this;
  }


  public function withoutDeleted()
  {
    if( $this->canBeDeleted() && ! $this->with_deleted ){

      $this->query()->whereNull( static::$_DELETED_AT );
    }

    return $this;
  }


  public function delete()
  {
    if($this->exists()){
      pr(['usr' => __FUNCTION__, 'exists 000' => $this->exists(), 'attributes' => $this->attributes]);

      $this->setAttribute( static::$_DELETED_AT, $this->dateTimeString() );

      if($this->save()){
        pr(['usr' => __FUNCTION__, 'exists 111' => $this->exists(), 'attributes' => $this->attributes]);

        return ! $this->exists = !! $this->attributes = [];
      }
    }
    pr(['usr' => __FUNCTION__, 'exists 222' => $this->exists(), 'attributes' => $this->attributes]);

    return null;
  }


  protected function softDeletesSafeCall($method)
  {
    if(method_exists( $this, $method)){

      return call_user_func( [$this, $method], ...func_get_args() );
    }

    return null;
  }


}
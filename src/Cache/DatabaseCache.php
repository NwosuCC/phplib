<?php

namespace Orcses\PhpLib\Cache;

use Orcses\PhpLib\Models\Model;
use InvalidArgumentException;
use Orcses\PhpLib\Interfaces\Modelable;


class DatabaseCache extends Cache implements Modelable
{
  protected $table = 'cache';

  /** @var \Orcses\PhpLib\Models\Model $model */
  protected $model;

  protected static $cache;


  protected function __construct(){
    parent::__construct();

    $this->model = Model::pseudo( $this );

    static::$cache = $this;
  }


  public function getTable(){
    return $this->table;
  }


  /** @return self */
  protected static final function cache(){
    if( ! static::$cache){
      new static();
    }

    return static::$cache;
  }


  /**
   * Retrieves and returns the value of the cached model using the key
   *
   * @param string  $key
   * @return string
   */
  public static function get(string $key)
  {
    $where = [
      'key' => $key,
      'expiration' => ['>', time()],
    ];

    $cache = static::cache()->model->where($where)->first();

    return $cache->{'value'} ?? null;
  }


  /**
   * Updates the cache specified by $key with new values
   * Alternative approach is to 'create' new row every time instead of 'update' existing row
   * @param string $key
   * @param string $value
   * @param int $expiration
   * @return bool
   */
  public static function store(string $key, string $value, int $expiration = 0)
  {
    if($expiration < 0){
      throw new InvalidArgumentException('int $expiration must be zero or a positive integer');
    }
    else if($expiration === 0){
      // Add 1 year to current Unix timestamp to mimic Infinity
      $y_1 = (int) (60 * 60 * 24 * 365.25 * 1);
      $expiration = time() + $y_1;
    }

    $new_values = [
      'key' => $key, 'value' => $value, 'expiration' => $expiration
    ];

    $where = ['key' => $key];

    return
      !! (static::cache()->model->where($where)->update($new_values))
      or
      !! (static::cache()->model->create($new_values));
  }



}
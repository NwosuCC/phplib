<?php

namespace Orcses\PhpLib\Models;


use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Utility\Str;
use Orcses\PhpLib\Utility\Dates;
use Orcses\PhpLib\Interfaces\Modelable;
use Orcses\PhpLib\Database\Query\MysqlQuery;
use Orcses\PhpLib\Database\Connection\MysqlConnection;
use Orcses\PhpLib\Database\Connection\ConnectionManager;
use Orcses\PhpLib\Exceptions\ModelAttributeNotFoundException;
use Orcses\PhpLib\Exceptions\ModelTableNotSpecifiedException;


abstract class Model
{
  protected $model_name;

  protected $exists = false;

  protected $original = [];

  protected $attributes = [];

  protected $fillable = [], $force_fill = false;

  protected $guarded = [];

  protected $hidden = [];

  protected $appends = [];


  protected $dates = [];

  /**
   * There must be exactly two timestamp columns: [0] for create, [1] for update
   */
  protected static $timestamps = [
    'created_at', 'updated_at'
  ];


  private $connection;

  private $table_columns = [];


  protected $pseudo_object, $lazy_load = false;


  protected $table;

  protected $key;

  protected $string_key;

  protected $auto_increment;


  protected $query;

  protected $result = false;

  protected $wheres = [], $orWheres = [], $order, $limit, $tempSql;

  protected $rows = [], $single;


  public function __construct(array $attributes = [])
  {
    $this->fill($attributes);
  }


  protected static function instance($attributes = [])
  {
    return new static($attributes);
  }


  /**
   * Creates and returns a PseudoModel instance for non-Model objects to query the database
   *
   * @param Modelable  $object
   * @return \Orcses\PhpLib\Models\PseudoModel
   */
  public static function pseudo(Modelable $object)
  {
    return new PseudoModel($object);
  }


  /**
   * Retrieves and returns an instance of the child model $obj having the specified $id
   * For use esp. in DI into Controllers
   *
   * @param Model   $obj  The child model object
   * @param string  $id   The id of the object
   * @return Model
   */
  public static function newFromObj(Model $obj, string $id)
  {
    return $obj->find( $id );
  }


  public function find(string $id)
  {
    return $this->where([ $this->getKeyName() => $id ])->first();
  }


  /**
   * Used to hydrate rows fetched from database into model instances
   * @param array $attributes
   * @return static
   */
  protected function newFromExisting(array $attributes)
  {
    $model = clone $this;

    $model->attributes = $model->original = $attributes;

    $model->exists = true;

    $model->setAppendedAttributes();

    $model->stripHidden();

    $model->single = true;

    return $model;
  }


  /**
   * Returns a fresh model instance
   */
  public function refresh()
  {
    $this->result = null;

    return $this;
  }


  // ToDo: refactor this
  protected function setConnection()
  {
    $this->connection = new MysqlConnection();
  }


  protected function getConnection()
  {
    if( ! $this->connection){
      $this->setConnection();
    }

    return $this->connection;
  }


  /**
   * Returns a fresh database query instance
   */
  protected function query()
  {
    if( ! $this->query){
      $this->query = new MysqlQuery( $this->getConnection() );
    }

    return $this->query;
  }


  public function getModelName()
  {
    if( ! $this->model_name){
      $model_path = Arr::stripEmpty( explode('\\', get_class($this) ));

      $this->model_name = end($model_path);
    }

    return $this->model_name;
  }


  protected function getDates()
  {
    return array_merge( static::$timestamps, array_values($this->dates) );
  }


  protected function updateTimestamps()
  {
    foreach(static::$timestamps as $i => $timestamp){

      if( array_key_exists($timestamp, $this->attributes)){

        // Skip the 'created_at' column if model already exists
        if($i === 0 && $this->exists()){
          continue;
        }

        $this->attributes[ $timestamp ] = Dates::now();
      }
    }

    return $this;
  }


  public function hasAttribute($key)
  {
    return array_key_exists($key, $this->attributes);
  }


  public function getAttributes(array $columns = [])
  {
    $available_columns = Arr::getExistingKeys($this->attributes, $columns);

    return $columns
      ? Arr::pickOnly($this->attributes, array_keys($available_columns))
      : $this->attributes;
  }


  public function getAttribute($key)
  {
    if( ! $this->hasAttribute($key)){
      throw new ModelAttributeNotFoundException($this->getModelName(), $key);
    }

    // ToDo: implement ArrayAccess and Iterator to use array objects in this Model
    // ToDo: For now, just casting model to array by default
//    return $this->attributes->{$key};

    return $this->attributes[ $key ];
  }


  public function setAttribute(string $key, $value)
  {
    return $this->fill([$key => $value]);
  }


  public function imposeAttribute(string $key, $value)
  {
    $this->attributes[ $key ] = $value;

    return $this;
  }


  public function removeAttribute(string $key)
  {
    unset( $this->attributes[ $key ] );

    return $this;
  }


  public function removeAttributes(array $columns = [])
  {
    foreach ($columns as $key){
      $this->removeAttribute( $key );
    }

    return $this;
  }


  public function setAppendedAttributes()
  {
    foreach ($this->appends as $append){
      $append_name = Str::titleCase( $append );

      $method_name = 'get' . ucfirst($append_name) . 'Attribute';

      if(method_exists($this, $method_name)){
        $this->attributes[ $append ] = $this->{$method_name}();
      }
    }
  }


  public function isFillable(string $key)
  {
    // Every pseudo-model formed via the Builder class will have all its properties fillable
    // It is advised to NOT use the Builder class for models directly exposed to or manipulated by users
    if($this->force_fill || is_a($this, PseudoModel::class) && $this->pseudo_object){
      return true;
    }

    return in_array($key, $this->fillable);
  }


  public function isNotFillable(string $key)
  {
    return ! $this->isFillable($key);
  }


  public function isGuarded(string $key)
  {
    return in_array($key, $this->guarded);
  }


  public function isUnGuarded(string $key)
  {
    return ! $this->isGuarded($key);
  }


  protected function stripGuarded()
  {
    return $this->removeAttributes( $this->guarded );
  }


  protected function stripHidden()
  {
    return $this->removeAttributes( $this->hidden );
  }


  /**
   * Fill the model with an array of attributes.
   * @param  array  $attributes
   * @return $this
   */
  public function fill(array $attributes)
  {
    foreach ($this->getValidAttributes($attributes) as $key => $value) {

      if ($this->isFillable($key)) {
        $this->attributes[ $key ] = $value;
      }
    }

    return $this;
  }


  public function forceFill(array $attributes)
  {
    $this->force_fill = true;

    $this->fill($attributes);

    $this->force_fill = false;

    return $this;
  }


  public function getChanges()
  {
    $valid_changes = [];

    foreach ($this->attributes as $key => $value){

      if(isset($this->original[ $key ]) && $value !== $this->original[ $key ]){

        $valid_changes[ $key ] = $value;
      }
    }

    return $valid_changes;
  }


  private function getTableNameFromModel()
  {
    $model_name = Str::snakeCase( $this->getModelName() );

    return $this->query()->tableExists( $model_name ) ? $model_name : '';
  }


  protected function setTable(string  $table = null)
  {
    if( ! $table and ! $table = $this->getTableNameFromModel()){
      throw new ModelTableNotSpecifiedException( $this->getModelName() );
    }

    $this->table = $table;

    if($this->table && $this->lazy_load){
      $this->lazy_load = false;

      $this->fill([]);
    }

    return $this;
  }


  public function getTable()
  {
    if( ! $this->table){
      $this->setTable();
    }

    if( ! $this->query()->table()){
      $this->query()->table( $this->table );
    }

    return $this->table;
  }


  private function getTableColumns()
  {
    if( ! $this->table_columns and ! $this->lazy_load){

      $columns = $this->query()->getTableColumns( $this->getTable() );

      $string_key_name_format = $this->getStringKeyNameFormat();

      foreach ($columns as $column){
        if($column->Extra === 'auto_increment' &&  ! $this->getAutoIncrementColumn()){
          $this->setAutoIncrementColumn( $column->Field );
        }

        if($column->Key === 'PRI' &&  ! $this->getKeyName()){
          $this->setKeyName( $column->Field );
        }

        if($column->Field === $string_key_name_format){
          $this->setStringKeyName( $column->Field );
        }

        $this->table_columns[ $column->Field ] = $column->Default;
      }
    }

    return $this->table_columns;
  }


  private function getValidAttributes(array $attributes)
  {
    return array_intersect_key( $attributes, $this->getTableColumns() );
  }


  private function setAutoIncrementColumn(string $column)
  {
    $this->auto_increment = $column;
  }


  public function getAutoIncrementColumn()
  {
    return $this->auto_increment;
  }


  private function setKeyName(string $column)
  {
    $this->key = $column;
  }


  public function getKeyName()
  {
    return $this->key;
  }


  public function getKey()
  {
    try {
      return $this->getAttribute( $this->key );
    }
    catch (ModelAttributeNotFoundException $e){}

    return null;
  }


  private function getStringKeyNameFormat()
  {
    $model_name = Str::snakeCase( $this->getModelName() );

    return $model_name . '_id';
  }


  private function setStringKeyName(string $column)
  {
    // If the developer has specified a string_key_name via the getStringKeyName() in the model, use it instead
    if($string_key_name = $this->getStringKeyName()){
      $column = $string_key_name;
    }

    $this->string_key = $column;
  }


  public function getStringKeyName()
  {
    return $this->string_key;
  }


  public function getStringKey()
  {
    return $this->getAttribute( $this->string_key );
  }


  public function currentSql()
  {
    return $this->query()->currentVars();
  }


  public function previousSql()
  {
    return $this->query()->prevSql();
  }


  public function orWhere($column, $operator = null, $value = null)
  {
    $this->query()->orWhere( $column, $operator, $value );

    return $this;
  }


  public function andWhere($column, $operator = null, $value = null)
  {
    $this->query()->andWhere( $column, $operator, $value );

    return $this;
  }


  public function where($column, $operator = null, $value = null)
  {
    $this->query()->where( $column, $operator, $value );

    return $this;
  }


  public function orderBy( string $column, string $direction = 'ASC'){
    $this->query()->orderBy($column, $direction);

    return $this;
  }


  public function limit( int $length, int $start = 0 ){
    $this->query()->limit($length, $start);

    return $this;
  }


  public static function all() {
    ($model = static::instance())
      ->query()
      ->whereRaw(['query' => '1', 'values' => []]);

    return $model->get();
  }


  public function get(){
    if( ! $this->result){
      $this->result = true;

      $rows = $this->query()->select()->all();

//      $this->rows = new \ArrayObject( $rows );
      $this->rows = $rows;

      $this->hydrate();
    }

    return $this;
  }


  /**
   * Returns all rows with only the specified columns
   * @param array $columns The columns to pick. All other columns are dropped
   * @return $this
   */
  public function only(array $columns)
  {
    return $this->some( 'only', $columns);
  }


  /**
   * Returns all rows with only the specified columns
   * @param array $columns The columns to pick. All other columns are dropped
   * @return $this
   */
  public function except(array $columns)
  {
    return $this->some( 'except', $columns);
  }


  protected function some(string $filter, array $columns)
  {
    $filters = [
      'only' => 'array_diff',
      'except' => 'array_intersect'
    ];

    if( ! array_key_exists($filter, $filters)){
      return null;
    }

    if($this->rows){
      $attributes = array_keys( $this->rows[0]->attributes );

      $filter_function = $filters[ $filter ];

      $drop_columns = array_values(
        call_user_func($filter_function, $attributes, $columns)
      );

      foreach ($this->rows as &$row){
        $row = $row->removeAttributes($drop_columns);
      }
    }

    return $this;
  }


  public function each($callback, ...$arguments)
  {
    array_map(function(&$value) use ($callback, $arguments){

      return call_user_func($callback, $value, ...$arguments);

    }, $this->rows());
  }


  public function filter($callback, bool $count = false, ...$arguments)
  {
    $selected = array_filter($this->rows(), function($value) use ($callback, $arguments){

      return call_user_func($callback, $value, ...$arguments);

    });

    return $count ? count($selected) : $selected;
  }


  public function any($callback, ...$arguments)
  {
    return !! $this->filter( $callback, true, $arguments);
  }


  public function toArray()
  {
    return $this->dehydrate();
  }


  protected function hydrate()
  {
    foreach($this->rows as &$row){
      $row = $this->newFromExisting( (array) $row );
    }
  }


  protected function dehydrate() {
    $multiple = empty($this->single);

    $rows = $multiple ? $this->rows : [$this];

    foreach($rows as &$row){
      $row = $row->getAttributes();
    }

    return $multiple ? $rows : $rows[0];
  }


  public function rows()
  {
    if( ! $this->result){
      $this->get();
    }

    return $this->rows;
  }


  public function count()
  {
    return count( $this->rows() );
  }


  public function first()
  {
    return ($rows = $this->rows()) ? reset($rows) : null;
  }


  public function last()
  {
    return ($rows = $this->rows()) ? end($rows) : null;
  }


  public function exists(){
    return $this->exists;
  }


  private function setNewStringId()
  {
    if($string_key_name = $this->getStringKeyName()){

      if($id_group = $this->query()->uniqueStringId( $string_key_name )){

        $this->setAttribute( $string_key_name, array_shift($id_group) );
      }
    }

    return $this;
  }


  private function performInsert()
  {
    $this->updateTimestamps();

    // ToDo: remove dryRun
//    return $this->query()->dryRun()->asTransaction(function (){
    return $this->query()->asTransaction(function (){

      $insert = $this->query()->insert( $this->attributes );

      return $insert;

    });
  }


  private function performUpdate()
  {
    if( ! $this->wheres){

      if($key_name = $this->getKeyName()){

        $this->where([ $key_name => $this->getKey() ]);
      }
      elseif($string_key_name = $this->getStringKeyName()){

        $this->where([ $string_key_name => $this->getStringKey() ]);
      }
    }

    if($this->currentSql()['where']){

      if($update_values = $this->getChanges()){

        $this->updateTimestamps();

        // ToDo: remove dryRun
//        return $this->query()->dryRun()->asTransaction(function () use($update_values){
        return $this->query()->asTransaction(function () use($update_values){

          return $this->query()->update( $update_values );

        });
      }
    }

    return false;
  }


  public function create(array $values){
    //ToDo: ...

    $this->fill($values);

    return $this->save();
  }


  public function update(array $values){
    //ToDo: ...

    $this->fill($values);

    return $this->save();
  }


  public function save(){
    return $this->exists() ? $this->performUpdate() : $this->setNewStringId()->performInsert();
  }


  public function __get(string $attribute)
  {
    return $this->getAttribute($attribute);
  }


  public function __toString()
  {
    return json_encode( $this->toArray() );
  }


}


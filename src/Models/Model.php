<?php

namespace Orcses\PhpLib\Models;


use Orcses\PhpLib\Database\Connection\MysqlConnection;
use Orcses\PhpLib\Database\Query\MysqlQuery;
use Orcses\PhpLib\Interfaces\Modelable;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Utility\Dates;
use Orcses\PhpLib\Utility\Str;
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

  protected $where, $orWhere, $order, $limit, $tempSql;

  protected $rows = [];


  public function __construct(array $attributes = []){
    $this->fill($attributes);
  }


  protected static function instance($attributes = []){
    return new static($attributes);
  }


  /**
   * Creates and returns a PseudoModel instance for non-Model objects to query the database
   *
   * @param Modelable  $object
   * @return \Orcses\PhpLib\Models\PseudoModel
   */
  public static function pseudo(Modelable $object){
    pr(['ModelAccess' => $object->getTable().': '.get_class($object)]);
//    $model = static::instance();

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
  public static function newFromObj(Model $obj, string $id){
    return $obj->where([ $obj->getKeyName() => $id ])->first();
  }


  /**
   * Used to hydrate rows fetched from database into model instances
   * @param array $attributes
   * @return static
   */
  protected function newFromExisting(array $attributes){
    pr(['newFromExisting pseudo_object $this' => $ao = $this->pseudo_object, 'table' => $ao ? $ao->getTable() : null]);
    $model = clone $this;

    pr(['newFromExisting pseudo_object $model' => $ao = $model->pseudo_object, 'table' => $ao ? $ao->getTable() : null]);

    $model->attributes = $attributes;

    $model->exists = true;

    $model->setAppendedAttributes();

    return $model;
  }


  // ToDo: refactor this
  protected function setConnection(){
    $this->connection = new MysqlConnection();
  }


  protected function getConnection(){
    if( ! $this->connection){
      $this->setConnection();
    }

    return $this->connection;
  }


  protected function query(){
    if( ! $this->query){
      $this->query = new MysqlQuery( $this->getConnection() );
    }

    return $this->query;
  }


  public function getModelName(){
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


  public function hasAttribute($key){
    return array_key_exists($key, $this->attributes);
  }


  public function getAttributes(){
    return $this->attributes;
  }


  public function getAttribute($key){
    if( ! $this->hasAttribute($key)){
      throw new ModelAttributeNotFoundException($this->getModelName(), $key);
    }

    // ToDo: implement ArrayAccess and Iterator to use array objects in this Model
    // ToDo: For now, just casting model to array by default
//    return $this->attributes->{$key};

    return $this->attributes[ $key ];
  }


  public function setAttribute(string $key, $value){
    return $this->fill([$key => $value]);
  }


  public function imposeAttribute(string $key, $value){
    $this->attributes[ $key ] = $value;

    return $this;
  }


  public function setAppendedAttributes(){
    foreach ($this->appends as $append){
      $append_name = Str::titleCase( $append );

      $method_name = 'get' . ucfirst($append_name) . 'Attribute';

      if(method_exists($this, $method_name)){
        $this->attributes[ $append ] = $this->{$method_name}();
      }
    }
  }


  public function isFillable(string $key){
    pr(['isFillable $key' => $key, 'class' => get_class($this), 'object' => $this->pseudo_object, 'lazy' => $this->lazy_load]);

    // Every pseudo-model formed via the Builder class will have all its properties fillable
    // It is advised to NOT use the Builder class for models directly exposed to or manipulated by users
    if($this->force_fill || is_a($this, PseudoModel::class) && $this->pseudo_object){
      return true;
    }

    return in_array($key, $this->fillable);
  }


  public function isNotFillable(string $key){
    return ! $this->isFillable($key);
  }


  public function isGuarded(string $key){
    return in_array($key, $this->guarded);
  }


  public function isUnGuarded(string $key){
    return ! $this->isGuarded($key);
  }


  protected function stripGuarded(){
    return $this->except( $this->guarded );
  }


  /**
   * Fill the model with an array of attributes.
   * @param  array  $attributes
   * @return $this
   */
  public function fill(array $attributes)
  {
    foreach ($val = $this->getValidAttributes($attributes) as $key => $value) {

      if ($this->isFillable($key)) {
        $this->attributes[ $key ] = $value;
      }
    }
    pr(['fill getValidAttributes' => $val]);
    pr(['fill fillables' => $this->fillable]);
    pr(['fill values' => $attributes]);
    pr(['fill new $attributes' => $this->getChanges()]);
    pr(['fill table_columns' => $this->table_columns]);
    pr(['fill class' => get_class($this)]);
    pr(['fill lazy_load' => $this->lazy_load]);

    return $this;
  }


  public function forceFill(array $attributes){
    $this->force_fill = true;

    $this->fill($attributes);

    $this->force_fill = false;

    return $this;
  }


  public function getChanges()
  {
    return array_diff_assoc($this->attributes, $this->original);
  }


  private function getTableNameFromModel(){
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
      pr(['reloadTable' => get_class($this)]);
      $this->lazy_load = false;

      $this->fill([]);
    }

    return $this;
  }


  public function getTable(){
    pr(['getTable' => get_class($this), '>pseudo_object' => $this->pseudo_object]);
    if( ! $this->table){
      $this->setTable();
    }

    if( ! $this->query()->table()){
      $this->query()->table( $this->table );
    }

    return $this->table;
  }


  private function getTableColumns(){
    pr(['getTableColumns table' => $this->table]);
    pr(['getTableColumns table_columns' => $this->table_columns]);
    pr(['getTableColumns lazy_load' => $this->lazy_load]);
    pr(['getTableColumns class' => get_class($this)]);
    pr(['getTableColumns pseudo_object' => $this->pseudo_object]);
    if( ! $this->table_columns and ! $this->lazy_load){

      pr(['getTableColumns again??' => $this->getTable()]);
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


  private function getValidAttributes(array $attributes){
    pr(['getValidAttributes class' => get_class($this)]);
    pr(['getValidAttributes $values' => $attributes]);
    pr(['getValidAttributes table' => $this->table]);

    return array_intersect_key( $attributes, $this->getTableColumns() );
  }


  private function setAutoIncrementColumn(string $column){
    $this->auto_increment = $column;
  }


  public function getAutoIncrementColumn(){
    return $this->auto_increment;
  }


  private function setKeyName(string $column){
    $this->key = $column;
  }


  public function getKeyName(){
    return $this->key;
  }


  public function getKey(){
    try {
      return $this->getAttribute( $this->key );
    }
    catch (ModelAttributeNotFoundException $e){}

    return null;
  }


  private function getStringKeyNameFormat(){
    $model_name = Str::snakeCase( $this->getModelName() );

    return $model_name . '_id';
  }


  private function setStringKeyName(string $column){
    // If the developer has specified a string_key_name via the getStringKeyName() in the model, use it instead
    if($string_key_name = $this->getStringKeyName()){
      $column = $string_key_name;
    }

    $this->string_key = $column;
  }


  public function getStringKeyName(){
    return $this->string_key;
  }


  public function getStringKey(){
    return $this->getAttribute( $this->string_key );
  }


  public function currentSql() {
    return $this->query()->currentVars();
  }


  public function previousSql() {
    return $this->query()->prevSql();
  }


  public function orWhere(array $columns_values){
    $this->query()->orWhere( $this->orWhere = $columns_values );

    return $this;
  }


  public function where(array $columns_values){
    $this->query()->where( $this->where = $columns_values );

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
      ->whereRaw(['query' => '1', 'values' => []]) // WHERE 1
      ->select();

    return $model->rows();
  }


  public function hydrate() {
    pr(['hydrate 111' => get_class($this), 'rows' => $this->rows]);
    pr(['hydrate pseudo_object' => $ao = $this->pseudo_object, 'table' => $ao ? $ao->getTable() : null]);

    foreach($this->rows as &$row){
      $row = $this->newFromExisting((array) $row);
      pr([
        'hydrate lazy_load' => $row->lazy_load,
        'hydrate class' => get_class($row),
        'hydrate getTable' => method_exists($row, 'getTable'),
        'hydrate table' => $row->getTable()
      ]);
    }
  }


  public function dehydrate() {
    foreach($this->rows as &$row){
      $row = $row->getAttributes();
    }
  }


  protected function rows() {
    pr(['rows 000' => get_class($this), 'rowsss' => $this->rows]);

    if( ! $this->result){
      $this->result = true;

      $this->rows = $this->query()->select()->all();

      $this->hydrate();
    }

    return $this->rows;
  }


  public function count() {
    return count( $this->rows() );
  }


  public function first(){
    $first = ($rows = $this->rows()) ? reset($rows) : null;
    pr(['$first' => $first]);
    return $first;
  }


  public function last(){
    return ($rows = $this->rows()) ? end($rows) : null;
  }


  /**
   * Returns all rows with only the specified columns
   * @param array|string $columns The columns to pick. All other columns are dropped
   * @return array
   */
  public function only($columns){
    return Arr::each($this->rows(), [Arr::class, 'pickOnly'], (array) $columns);
  }


  /**
   * Returns all rows with only the specified columns
   * @param array|string $columns The columns to pick. All other columns are dropped
   * @return array
   */
  public function except($columns){
    return Arr::each($this->rows(), [Arr::class, 'pickExcept'], (array) $columns);
  }


  public function exists(){
    return $this->exists;
  }


  private function setNewStringId(){
    if($string_key_name = $this->getStringKeyName()){

      if($id_group = $this->query()->uniqueStringId( $string_key_name )){

        $this->setAttribute( $string_key_name, array_shift($id_group) );
      }
    }

    return $this;
  }


  public function get(){
    return $this->rows();
  }


  private function castToDefaultType($result){
    return (array) $result;
  }


  private function performInsert(){
    $this->updateTimestamps();

    // ToDo: remove dryRun
    return $this->query()->dryRun()->asTransaction(function (){
//    return $this->db()->asTransaction(function (){

      $insert = $this->query()->insert( $this->attributes );
      pr(['$insert' => $insert]);

      return $insert;

    });
  }


  private function performUpdate()
  {
    pr( ['where' => $this->where, 'attributes' => $this->attributes, 'original' => $this->original]);
    if( ! $this->where){
      if($key_name = $this->getKeyName()){
        $this->where([ $key_name => $this->getKey() ]);
      }
      elseif($string_key_name = $this->getStringKeyName()){
        $this->where([ $string_key_name => $this->getStringKey() ]);
      }
    }

//    pr(['getKeyName' => $this->getKeyName(), 'getStringKeyName' => $this->getStringKeyName()]);
    pr( ['currentVars' => $this->query()->currentVars() ]);

    if($this->where){
      $this->updateTimestamps();

      // ToDo: remove dryRun
      $update_values = $this->getChanges();
      pr(['$update_values' => $update_values]);

      if($update_values){
//      if($update_values = $this->getChanges()){

        return $this->query()->dryRun()->asTransaction(function () use($update_values){
//    return $this->db()->asTransaction(function (){

          $update = $this->query()->update( $update_values );
          pr(['$update' => $update]);

          return $update;
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
//    $this->lazy_load = false;


    $this->original = $this->attributes;

    $this->fill($values);
    pr(['update $values' => $values, 'attributes' => $this->attributes, 'changes' => $this->getChanges()]);

    return $this->save();
  }


  public function save(){
    $result = $this->exists()
      ? $this->performUpdate()
      : $this->setNewStringId()->performInsert();

    if($result){
      $this->attributes = $this->castToDefaultType( $result );
    }

    return !empty($result);
  }


  public function __get(string $attribute)
  {
    return $this->getAttribute($attribute);
  }


  public function toArray() {
    $this->rows();

    $this->dehydrate();

    return (array) $this->rows;
  }


  public function __toString()
  {
    return json_encode( $this->toArray() );
  }


}


<?php

namespace Orcses\PhpLib;

use mysqli as MySQLi;
use Orcses\PhpLib\Incs\CustomErrorHandler;
use Orcses\PhpLib\Incs\HandlesError;


class DB implements CustomErrorHandler {

  use HandlesError;


  private static $sql, $connection, $error_handler;

  public static $result, $rows = [];


  // See CustomErrorHandler::setErrorHandler() for more info
  public static function setErrorHandler(array $callback = []) {
    static::$error_handler = $callback;
  }

  // See CustomErrorHandler::getErrorHandler() for more info
  public static function getErrorHandler() {
    return static::$error_handler;
  }


  public static final function connection($db = '') {
    if( !static::$connection){
      self::check_parameters();

      $db = $db ?: DB_DATABASE;

      static::$connection = new MySQLi(DB_HOST,DB_USERNAME,DB_PASSWORD, $db);
    }

    return static::$connection;
  }


  public static function check_parameters() {
    $parameters = [
      'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'
    ];

    if($error = requires($parameters, false)){
      static::throwError($error);
      exit;
    }
  }


  public static function close() {
    static::connection()->close();
  }


  public static function escape(array $values){
    $escaped_values = [];

    foreach($values as $key => $value){
      if(is_array($value)){
        $escaped_values[ $key ] = static::escape($value);
      }
      else{
        $value = stripslashes( htmlspecialchars( trim($value)));
        $escaped_values[$key] = static::connection()->real_escape_string($value);
      }
    }

    return $escaped_values;
  }


  /**
   * E.g given " w.status  w.id ", returns ['w.status', 'w.id']
   * @param string $value The string to split and trim
   * @param string $delimiter The delimiter character to use for the split
   * @return array
   */
  private static function splitByChar(string $value, string $delimiter){
    $parts = array_map(function($str){ return trim($str); }, explode($delimiter, $value));

    return array_filter($parts, function($str){ return $str !== ''; });
  }


  /**
   * E.g given "w.status", returns ['w', 'status']
   * @param string $column
   * @return array
   */
  private static function getTableAliases(string $column){
    $parts = static::splitByChar($column, '.');

    return Utility::pad_array($parts, 2, '', false);
  }

  /**
   * E.g given "w.status as withdrawal_status", returns ['w.status', 'withdrawal_status']
   * @param string $column
   * @return array
   */
  private static function getColumnAliases($column){
    $aliases = static::splitByChar($column, 'as');

    return Utility::pad_array($aliases, 2, '');
  }

  /**
   * Catches any single quote that is NOT already escaped
   * @param string $value
   * @param string $char
   * @return bool
   */
  private static function hasUnescapedSingleQuote($value, $char){
    $quote_index = strpos($value, $char);
    $slash_index = strpos($value,"\\");

    $has_quote = $quote_index !== false;
    $quote_is_unescaped = ($quote_index - 1) !== $slash_index;

    return $has_quote && $quote_is_unescaped;
  }

  /**
   * For Database queries: - Either add single-quotes around values
   *                       - Or add back-quotes around columns
   * @param array $array The columns or values to add quotes to
   * @param string $mode One of ['select', 'insert', 'update']
   * @return array
   */
  // Select : column|value|sub-query as alias
  // Insert - column, value|sub-query
  // Update - column, value|sub-query
  public static function add_quotes(array $array, string $mode){
    $quotes = ['column' => "`", 'value' => "'"];

    $modes = [ 'select', 'insert', 'update' ];

    if( !in_array($mode = trim($mode), $modes)){
      static::throwError(
        "Function DB::add_quotes() requires parameter 'mode' to be one of ['select', 'insert', 'update']"
      );
    }

    foreach($array as $key => $value){
//      $quote = $types[ $type ] ?? $types['value'];

//      list($value, $alias) = static::getColumnAliases($column);
      $column = $alias = null;

      $column_quote = $alias_quote = $value_quote = '';

      $column_value = $column_sub_query = false;


      if($mode === 'select'){
        list($column, $alias) = static::getColumnAliases($value);
        $value = null;
      }
      if($mode === 'insert'){
        $column = $key;
      }
      if($mode === 'update'){
        $column = $key;
      }

      // ToDo: examine this
      /*if(static::hasUnescapedSingleQuote($value, $quote)){
        // Empty the array and terminate the loop
        $array = [];
        break;
      }*/

      // TYPE: COLUMN
//      if($type === 'column'){
//        $column = $value;
//        $column_quote = $value_quote;

        if($alias){
          if(stripos($alias, '|q') !== false){
            // $column is a valid MySQL query/sub-query => no quotes
            $column_sub_query = true;
            $alias = str_replace('|q', '', $alias);
          }
          else if(stripos($alias, '|v') !== false){
            // $column is a Value - should be single-quoted
            $column_value = true;
            $alias = str_replace('|v', '', $alias);
          }

          $alias = "{$alias_quote}". trim($alias) ."{$alias_quote}";
        }

        if( ! $column_value && ! $column_sub_query ){
          // Then it must be a normal column name

          if(stristr($column, '.')){
            list($table, $column) = static::getTableAliases($column);
            $column = $table .'.' . "{$column_quote}". $column ."{$column_quote}";
          }
        }

        if($alias){
          $column = "$column as $alias";
        }

        $array[ $key ] = $column;
//      }

      // TYPE: VALUE
//      else if($type === 'value'){
        $value_column = $key;

        // $value is a valid MySQL query/sub-query => no quotes
        if(stripos($value_column, '|q') !== false){
          $value_sub_query = true;
          $value_quote = '';
          $value_column = str_replace('|q', '', $value_column);
        }

        $array[ $value_column ] = "{$value_quote}". $value ."{$value_quote}";
//      }
    }

    return $array;
  }

  /* Example usage: Shows usage of the 'q|' modifier ( also implemented in 'DB::where()' method )
   *  $delivery = "IF( MINUTE( TIMEDIFF(now(), created_at) ) BETWEEN 1 AND 2, 1, delivery)";
      $update_values = [
          'p_total' => $total, 'p_current' => $current, 'hits' => 'q|hits + 1',
          'count' => $reviewsCount, 'delivery' => "q|$delivery"
      ];
   */
  public static function add_single_quotes($var){
    $is_array = is_array($var);

    $array = $is_array ? $var : (array) $var;

    $result = static::add_quotes($array, 'value');

    return $is_array ? $result : array_shift($result);
  }


  public static function add_back_quotes($var){
    $is_array = is_array($var);

    $array = $is_array ? $var : (array) $var;

    $result = static::add_quotes($array, 'column');

    return $is_array ? $result : array_shift($result);
  }


  private function validateVars($queryType, $vars){
    switch ($queryType) {
      case 'insert' : {
        list($columns, $values) = $vars;

        if(empty($columns) or !is_array($columns) or empty($values) or !is_array($values)
          or !is_array($values[0]) or count($columns) !== count($values[0])){
          $error = 'DB::insert() requires valid Array $columns and Array $values.';
          $syntax1 = 'Array $columns: ["fruit","tally","isFavourite"]';
          $syntax2 = 'Array $values: [ ["pear",23,false], ["apple",57,true], ["orange",30,true] ]';
        }

      } break;

      case 'update' : {

      }

      default : {}
    }
  }

  public static function queryString() {
    $sql_string = static::$sql;
    static::$sql = '';

    return $sql_string;
  }

  
  public static function start_txn() {
    static::run("START TRANSACTION");
  }

  public static function end_txn($ok) {
    static::run(($ok === true) ? "COMMIT" : "ROLLBACK");
    static::close();
  }

  private static function run($sql){
    static::$sql = $sql;
    
    static::$result = static::connection()->query(static::$sql);

    if ( ! static::$result){
      static::throwError(
        static::connection()->error."; Problem with Query \"".static::$sql."\"\n"
      );
    }

    static::queryString();

    return static::$result;
  }

  public static function run_multi(Array $queries, Array $labels = [], $use_result = false){
    if( trim( end($queries) ) == ''){ array_pop($queries); }
    $queriesCount = count($queries);

    if( trim( end($labels) ) == ''){ array_pop($labels); }
    $labelsCount = count($labels);

    if($use_result and !empty($labels) and $queriesCount != $labelsCount){
      static::throwError(
        'Function run_multi(): Number of "Queries" combined in @param $queries' .
        ' must match Number of "Labels" provided in @param $labels'
      );
    }

    static::$sql = implode(';', $queries);
    $connection = static::connection();

    $fetch = [];

    if($connection->multi_query( static::$sql )){

      do {
        if(static::$result = $connection->store_result()){
          $group = (isset($group)) ? ++$group : 0;

          while($row = static::$result->fetch_assoc()){
            if($use_result){
              $key = !empty($labels) ? $labels[$group] : $group;
              $fetch[ $key ] = [$row];
            }
            else{
              $fetch = $group + 1;
            }
          }

          if( !isset($fetch)){ $fetch = false; }

          static::$result->free();
        }

      } while( $connection->more_results() and $connection->next_result() );


      if(isset($fetch)){
        return $fetch;

      }else{
        //  For operations like 'CREATE Table' which return (bool) FALSE on both 'Success' and 'Failure'
        return ($connection->error == '') ? true : null;
      }

    }else{
      static::throwError(
        $connection->error."; Problem with Query \"".static::$sql."\"\n"
      );
    }
  }


  private function where(Array $where){
    if( !$where){
      throw new Error('where() Example: [ "name = ?1 OR email = ?2", [$name, $email] ]');
    }

    if(count($where) < 2){ $where[] = []; }

    list($whereString, $vars) = $where;

    foreach ($vars as $index => $var){
      $pos_value = strpos($whereString, '?');
      $pos_query = strpos($whereString, '?q');
      $is_query = ($pos_query !== false and $pos_value === $pos_query);

      if( ! $is_query ){
        list($var) = $this->add_single_quotes( $this->escape([$var]) );
      }

      $number = ($index + 1);
      $pattern = "/\?[q]?{$number}([^0-9]|$)/";

      $n = 0;  $max_n = 100;

      while( preg_match($pattern, "$whereString", $matches) ) {
        $whereString = str_replace($matches[0], ($var.$matches[1]), $whereString);
        if(++$n > $max_n) {
          throw new Error("Specified maximum reasonable iterations ($max_n) exceeded");
        }
      }
    }

    return $where ? "WHERE {$whereString}" : '';
  }

  private function fetch(){
    if(static::$result){
      $this->rows = [];
      while($row = static::$result->fetch_assoc()){
        $this->rows[] = $row;
      }
    }
  }

  public function limit( int $length, int $start = 0 ){
    static::$sql .= " LIMIT {$start}, {$length}";
    $this->run_sql()->fetch();
    return $this->rows;
  }

  public function all(){
    $this->run_sql()->fetch();
    return $this->rows;
  }

  public function id() {
    $row = $this->first();
    return $row ? [ 'id' => $row['id'] ] : null;
  }

  public function first(){
    $this->run_sql()->fetch();
    return reset($this->rows);
  }

  public function last(){
    $this->run_sql()->fetch();
    return end($this->rows);
  }

  public function getLastInsert($table, $columns = []){
    $where = ['id = LAST_INSERT_ID()'];
    return $this->select($table, $columns, $where);
  }

  public static function select($table, Array $columns, Array $where = []){
    $columns = empty($columns) ? ['*'] : $columns;

    $columns = implode(',', static::escape($columns));

    $where = $where ? static::where($where) : '';

    self::$sql_string = "SELECT {$columns} FROM {$table} {$where}";

    return new DB;
  }


  public function insertUnique($table, Array $columns, Array $values, Array $where = []){
    $select_columns = $columns;
    if( ! array_search('id', $select_columns)){ $select_columns[] = 'id'; }

    $record = $this->select($table, $select_columns, $where)->first();

    return $record ? false : $this->insert($table, $columns, $values);
  }

  public function insertOrUpdate($table, Array $columns, Array $values, Array $update_values){
    if( !$update_values) {
      throw new Error('insertOrUpdate() requires parameter [Array $update_values]');
    }

    $allUpdateValues = [];

    foreach ($update_values as $column => $value){
      $value = $this->add_single_quotes($this->escape([$value]));
      $allUpdateValues[] = "$column = " . array_shift($value);
    }
    $allUpdateValues = implode(',', $allUpdateValues);

    static::$sql = " ON DUPLICATE KEY UPDATE {$allUpdateValues}";

    return $this->insert($table, $columns, $values);
  }

  public function insert($table, Array $columns, Array $values){
    $this->validateVars('insert', [$columns, $values]);

    $insert_columns = implode(',', $this->escape($columns));
    $values = $this->escape($values);

    $allValues = [];
    foreach ($values as $value){
      $value = $this->add_single_quotes($this->escape($value));
      $allValues[] = '(' . implode(',', $value) . ')';
    }
    $allValues = implode(',', $allValues);

    $sql_string = "INSERT INTO {$table} ({$insert_columns}) VALUES {$allValues}";

    static::$sql = $sql_string . static::$sql;

    $this->run_sql();

    return static::$result ? $this->getLastInsert($table, $columns)->first() : false;
  }

  public function update(string $table, Array $updates, Array $where) {
    $this->validateVars('update', [$updates]);

    $columns_values = [];

    foreach ($updates as $column => $value){
      $quoted_column = $this->add_back_quotes([$column]);
      $column = array_shift($quoted_column);

      $value = $this->add_single_quotes( $this->escape([$value]) );

      $columns_values[] = "$column = " . array_shift($value);
    }

    $columns_values = implode(',', $columns_values);

    $columns_values = "id = LAST_INSERT_ID(id), " . $columns_values;

    if($where = $where ? $this->where($where) : ''){
      static::$sql = "UPDATE {$table} SET {$columns_values} {$where}";

      $this->run_sql();

      if($result = $connection->affected_rows) {
        list($table, $columns) = Schema::get($table);
        return $this->getLastInsert($table, $columns)->first();
      }
    }

    return false;
  }


}

?>
<?php

namespace Orcses\PhpLib;

use mysqli as MySQLi;
use Orcses\PhpLib\Incs\CustomErrorHandler;
use Orcses\PhpLib\Incs\HandlesError;


class DB implements CustomErrorHandler {

  use HandlesError;


  private static $connection, $error_handler;

  private static $sql, $where, $order, $limit;

  private static $caller = '';

  public static $result, $rows = [], $assoc = false;


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

        // Escape if NOT marked as Raw MySQL Query using '|q'
        if(stripos($value, '|q') === false){
          $value = static::connection()->real_escape_string($value);
        }

        $escaped_values[$key] = $value;
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

  private static function isRawData(string $type, string$value){
    $types = ['c', 'q', 'v'];

    if(!in_array($type, $types)){
      static::throwError('DB::isRawData() - invalid argument "type" supplied');
    }

    $marker = "|$type";

    $value = trim($value);

    $has_marker = stripos($value, $marker) !== false;
    $ends_with_marker = strlen( stristr($value, $marker)) === 2;

    return $has_marker && $ends_with_marker;
  }

  private static function isTableColumn($value){
    return static::isRawData('c', $value);
  }

  private static function isRawQuery($value){
    return static::isRawData('q', $value);
  }

  private static function isRawValue($value){
    return static::isRawData('v', $value);
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
   * @param bool $select_mode One of ['select', 'insert', 'update']
   * @return array
   */
  public static function add_quotes_Columns(array $array, bool $select_mode = false){
    $quotes = ['column' => "`", 'value' => "'"];

//    $modes = [ 'select', 'insert', 'update' ];

    foreach($array as $key => $value){
      $column = $alias = $column_value = $column_sub_query = null;

      $table = $column_quote = $alias_quote = $value_quote = '';


      if($select_mode){
        list($column, $alias) = static::getColumnAliases($value);
        $value = null;
      }

      if($alias){
        if(static::isRawQuery($alias)){
          // $column is a valid MySQL query/sub-query => no quotes
          $column_sub_query = true;
          $alias = str_replace('|q', '', $alias);
        }
        else if(static::isRawValue($alias)){
          // $column is a Value - should be single-quoted
          $column_value = true;
          $alias = str_replace('|v', '', $alias);
        }

        $alias_quote = $quotes['column'];

        $alias = "{$alias_quote}". trim($alias) ."{$alias_quote}";
      }

      if( ! $column_sub_query ){
        // Then it must be a normal column name

        if( ! $column_value && stristr($column, '.')){
          list($table, $column) = static::getTableAliases($column);
          if($table){ $table .= '.'; }
        }

        $column_quote = $column_value ? $quotes['value'] : $quotes['column'];

        if(static::hasUnescapedSingleQuote($column, $column_quote)){
          // Column already has single quote (suspicious!!!). Empty the array and terminate the loop
          $array = [];
          break;
        }

        $column = $table . "{$column_quote}". $column ."{$column_quote}";
      }

      if($alias){
        $column = "$column as $alias";
      }

      $array[ $key ] = $column;
    }

    return $array;
  }

  public static function add_quotes_Values(array $array){
    $quotes = ['column' => "`", 'value' => "'"];

    foreach($array as $key => $value){
      $value_column = $value_sub_query = false;

      if(static::isTableColumn($value)){
        // $value is a valid table.column key
        $value_column = true;
        $value = str_replace('|c', '', $value);

        list($table, $column) = static::getTableAliases($value);
        if($table){ $table .= '.'; }

        $column_quote = $quotes['column'];

        $value = $table . "{$column_quote}". $column ."{$column_quote}";
      }
      else if(static::isRawQuery($value)){
        // $value is a valid MySQL query/sub-query => no quotes
        $value_sub_query = true;
        $value = str_replace('|q', '', $value);
      }

      if( ! $value_column && ! $value_sub_query ){
        // Then it must be a normal value

        $value_quote = $quotes['value'];

        $value = "{$value_quote}". $value ."{$value_quote}";
      }

      $array[ $key ] = $value;
    }

    return $array;
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


  private static function caller() {
    $caller = static::$caller;
    return (static::$caller = '') ?: $caller;
  }

  public static function queryString() {
    $sql = static::$sql;
    static::$sql = '';

    $where = static::$where;
    static::$where = '';

    return $sql . $where;
  }

  public static function start_txn() {
    static::$sql = "START TRANSACTION";
    static::run();
  }

  public static function end_txn($ok) {
    static::$sql = ($ok === true) ? "COMMIT" : "ROLLBACK";
    static::run();
    static::close();
  }

  private static function run(){
    static::$result = static::connection()->query( $sql = static::queryString() );

    if ( ! static::$result){
      static::throwError(
        static::connection()->error."; Problem with Query \"". $sql ."\"\n"
      );
    }

    return new static;
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

    if($connection->multi_query( $sql = static::queryString() )){

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
        $connection->error."; Problem with Query \"". $sql ."\"\n"
      );
    }
  }


  private static function validateQueryValues(array $values, string $query = ''){
    $values_count = count($values);

    if( ! $query){
      if($valid_keys = array_intersect(['query', 'values'], array_keys($values))){
        $query = $values['query'];
        $values = $values['values'];
      }
      else {
        $valid_items_count = $values_count != 2;
      }
    }

    $valid = !( !$values_count or (!$query && empty($valid_keys) && empty($valid_items_count)) );

    return $valid ? [$values, $query] : null;
  }


  private static function setQueryValues(array $values, string $query = ''){
    $valid_args = static::validateQueryValues($values, $query);

    if( ! $valid_args){
      $error = 'DB::setQueryValues requires arguments:'
        . ' EITHER i.) (array $values e.g [$name, $email], string $query e.g "name = ?1 OR email = ?2")'
        . ' OR ii.) (array $values e.g ["query" => "name = ?1 OR email = ?2", "values" => [$name, $email]])';

      // ToDo: store error message in json format so after getMessage(), one can json_decode(..., true) or use as valid json
      /*$error = json_encode([
        'DB::setQueryValues required arguments' => [
          'EITHER' => '(array $values, string $query)',
          'OR' => '(array $values) where $values structure is ["query" => $query, "values" => $values])'
        ]
      ]);*/

      if($caller = static::caller()){
        $error .= " in ". static::class ."::{$caller}()";
      }

      static::throwError($error);

      return [];
    }


    list($values, $query) = $valid_args;

    foreach ($values as $number => $var){
      $pos_value = strpos($query, '?');
      $pos_query = strpos($query, '?q');
      $is_query = ($pos_query !== false and $pos_value === $pos_query);

      if( ! $is_query ){
        list($var) = static::add_quotes_Values( static::escape([$var]) );
      }

      $pattern = "/\?[q]?{$number}([^0-9]|$)/";

      $n = 0;  $max_n = 100;

      while( preg_match($pattern, "$query", $matches) ) {
        $query = str_replace($matches[0], ($var.$matches[1]), $query);

        if(++$n > $max_n) {
          static::throwError("Specified maximum reasonable iterations ($max_n) exceeded");
        }
      }
    }

    return $query;
  }


  public static function whereRaw(array $values, string $query = ''){
    static::$caller = static::caller() ?: 'whereRaw';
    static::$where = 'WHERE ' . static::setQueryValues($values, $query);

    return new static;
  }


  public static function rawQuery(array $values, string $query = ''){
    static::$caller = static::caller() ?: 'rawQuery';
    return static::setQueryValues($values, $query);
  }


  public static function orWhere(array $columns_values){

  }

  public static function where_assoc(array $columns_values){
    if( !$columns_values){
      static::throwError('DB::where() Example: ["name" => $name, "email" => $email]');
    }

    $columns = $values = $update_values = $where = [];

    $op = 'w';

    foreach($columns_values as $column => $value){
      if(!is_array($value) or $op === 'w'){
        if(stripos($column, '||') === false){
          if(stripos($column, '|s') !== false){
            // Value is a MySql keyword|function e.g now(), so, should not be quoted
            $column = str_replace('|s', '', $column);
          }else{
            if(!is_array($value)){
              list($value) = static::add_quotes_Values([$value]);
            }
          }
        }

        if($op === 'w' and is_array($value)){
          $equals = ['=', '<', '<=', '>', '>='];
          if(empty($value[0])) dd($value);
          list($preposition, $vars) = $value;
          if(is_array($vars)){
            if(in_array($preposition, ['BETWEEN','NOT BETWEEN'])){
              $vars = "{$vars[0]} AND {$vars[1]}";
            }elseif(in_array($preposition, array_merge(['IN','NOT IN'], $equals))){
              $vars = array_values($vars);
              if(in_array($preposition, ['IN','NOT IN'])){
                $vars = static::add_quotes_Values($vars);
                $vars = '(' . implode(',', $vars) . ')';
              }
            }
          }

          // E.g: WHERE username||email = 'sean@mail.com'
          //      => "(username = 'sean@mail.com' OR email = 'sean@mail.com')"
          // Or:  WHERE username||email = ['Carl', 'sean@mail.com']
          //      => "(username = 'Carl' OR email = 'sean@mail.com')"
          if(stripos($column, '||') !== false){
            $column = explode('||', $column);  $val = [];
            foreach($column as $i => $col){
              $item = (is_array($vars)) ? $vars[$i] : $vars;
              if(in_array($preposition, $equals)){  // ['=', '<', '<=', '>', '>=']
                if(stripos($col, '|s') !== false){
                  $col = str_replace('|s', '', $col);
                }else{
                  list($item) = static::add_quotes_Values([$item]);
                }
              }

              list($col) = static::add_quotes_Columns([$col]);
              $val[] = "$col $preposition $item";
            }
            $update_values[] = '('. implode(' OR ', $val) . ')';
          }
          else{
            if(in_array($preposition, $equals)){  // ['=', '<', '<=', '>', '>=']
              if(stripos($column, '|s') !== false){
                $column = str_replace('|s', '', $column);
              }else{
                list($vars) = static::add_quotes_Values([$vars]);
              }
            }

            list($column) = static::add_quotes_Columns([$column]);
            $update_values[] = "$column $preposition $vars";
          }
        }else{
          list($column) = static::add_quotes_Columns([$column]);

          if(stristr($column, '_blank_')){
            // Use column name '_blank_' to insert only the sub-query value in a Where... clause
            $update_values[] = "$value";
          }
          else {
            $columns[] = $column;
            $values[] = $value;
            $update_values[] = "$column = $value";
          }
        }
      }
    }

    static::$where = "WHERE " . implode(' AND ', $update_values);
    return new static;
  }

  public function limit( int $length, int $start = 0 ){
    static::$limit = " LIMIT {$start}, {$length}";
    return $this;
  }

  private function fetch(){
    static::$rows = [];

    $function = static::$assoc ? 'fetch_assoc' : 'fetch_object';

    if (static::$result->num_rows){
      while( $row = static::$result->$function() ){
        static::$rows[] = $row;
      }
    }
  }

  public function asArray() {
    static::$assoc = true;
    return $this;
  }

  public function all(){
    static::run()->fetch();
    return static::$rows;
  }

  public static function count() {
    return static::$result->num_rows;
  }

  public function id(string $id_column = '') {
    $row = $this->first();

    $valid_column = $row && (!empty($row['id']) || empty($row[ $id_column ]));

    $id = $valid_column ? $row['id'] ?? $row[ $id_column ] : null;

    return $id ? ['id' => $id] : null;
  }

  public function first(){
    return ($rows = $this->all()) ? reset($rows) : null;
  }

  public function last(){
    return ($rows = $this->all()) ? end($rows) : null;
  }

  public function lastModifiedId($table, $columns = []){
    $where = ['id = LAST_INSERT_ID()'];
    return $this->select($table, $columns, $where)->last();
  }

  public static function select($table, Array $columns, Array $where = []){
    $columns = empty($columns) ? ['*'] : $columns;

    $columns = static::escape($columns);

    $columns = static::add_quotes_Columns($columns, true);

    $columns = implode(',', $columns);

    if($where){
      static::$caller = 'select';
      static::whereRaw( $where );
    }

    static::$sql = "SELECT {$columns} FROM {$table}";

    return new static;
  }


  public function insertUnique($table, Array $columns, Array $values, Array $where = []){
    $select_columns = $columns;
    if( ! array_search('id', $select_columns)){ $select_columns[] = 'id'; }

    $record = $this->select($table, $select_columns, $where)->first();

    return $record ? false : $this->insert($table, $columns, $values);
  }

  public function insertOrUpdate($table, Array $columns, Array $values, Array $update_values){
    if( !$update_values) {
      static::throwError('insertOrUpdate() requires parameter [Array $update_values]');
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

    static::run();

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

    if($where = $where ? $this->whereRaw($where) : ''){
      static::$sql = "UPDATE {$table} SET {$columns_values} {$where}";

      static::run();

      if($result = $connection->affected_rows) {
        list($table, $columns) = Schema::get($table);
        return $this->getLastInsert($table, $columns)->first();
      }
    }

    return false;
  }


}

?>
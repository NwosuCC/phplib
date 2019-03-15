<?php

namespace Orcses\PhpLib\Database\Query;

use mysqli as MySQLi;
use Orcses\PhpLib\Database\Connection\MysqlConnector;
use Orcses\PhpLib\Incs\HandlesErrors;
use Orcses\PhpLib\Incs\HandlesError;
use Orcses\PhpLib\Utility\Str;
use Orcses\PhpLib\Utility\Arr;


class MysqlQuery implements HandlesErrors {

  use HandlesError;


  /**
   * @var MySQLi instance
   */
  private $connection;

  private static $sqlMethod, $error_handler;

  private static $caller = '', $recursive = [];

  private static $sql, $where, $orWhere, $order, $limit;
  private static $multi = [], $isNewQuery = false;

  public static $result, $rows = [], $count = 0, $assoc = false;


  public function __construct(MysqlConnector $connector = null) {
    if( is_null($connector) ){
      $connector = new MysqlConnector();
    }

    $this->connection = $connector->connect();
  }


  /**
   * Returns a new instance of this class. Especially for use within static functions
   *@return $this
   */
  protected static function newQuery() {
    return new static();
  }


  // See CustomErrorHandler::setErrorHandler() for more info
  public static function setErrorHandler(array $callback = []) {
    static::$error_handler = $callback;
  }


  // See CustomErrorHandler::getErrorHandler() for more info
  public static function getErrorHandler() {
    return static::$error_handler;
  }


  /**
   * Prepares the query for execution. Escapes single- and double-quotes
   * @param array $values The values to escape
   * @return array
   */
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
          $value = static::newQuery()->connection->real_escape_string($value);
        }

        $escaped_values[$key] = $value;
      }
    }

    return $escaped_values;
  }


  /**
   * E.g given "w.status", returns ['w', 'status']
   * @param string $column
   * @return array
   */
  private static function getTableAliases(string $column){
    $parts = Str::splitByChar($column, '.');

    return Arr::pad($parts, -2, '');
  }

  /**
   * E.g given "w.status as withdrawal_status", returns ['w.status', 'withdrawal_status']
   * @param string $column
   * @return array
   */
  private static function getColumnAliases($column){
    $aliases = Str::splitByChar($column, 'as');

    return Arr::pad($aliases, 2, '');
  }

  private static function isRawData(string $type, string$value){
    $types = ['c', 'q', 'v', 'b'];

    if(!in_array($type, $types)){
      static::throwError('MysqlQuery::isRawData() - invalid argument "type" ['. $type .'] supplied');
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

  // $column is meant to be blank so only the corresponding value appears in the sql query
  private static function isBlank($value){
    return static::isRawData('b', $value);
  }

  // $column is a valid MySQL query/sub-query => no quotes
  private static function isRawQuery($value){
    return static::isRawData('q', $value);
  }

  // $column is a Value - should be single-quoted
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
   * @param array|string $columns The columns or values to add quotes to
   * @param bool $select_mode If true, columns are checked for aliases e.g "u.id as user_id"
   * @return array|string
   */
  public function add_quotes_Columns($columns, bool $select_mode = false){
    if( ! $is_array = is_array($columns)){
      $columns = [$columns];
    }

    $quotes = ['column' => "`", 'value' => "'"];

    $column_types = [];

    foreach($columns as $index => $column){
      $column_type = '';

      $alias = $column_value = $column_sub_query = $column_blank = null;

      $table = $column_quote = $alias_quote = $value_quote = '';


      if($select_mode){
        list($column, $alias) = static::getColumnAliases($column);
      }

      if(static::isBlank($column)){
        $column_type = $column_blank = 'b';
        $column = str_replace('|b', '', $column);
      }
      else if(static::isRawQuery($column)){
        $column_type = $column_sub_query = 'q';
        $column = str_replace('|q', '', $column);
      }
      else if(static::isRawValue($column)){
        $column_type = $column_value = 'v';
        $column = str_replace('|v', '', $column);
      }

      if( ! $column_sub_query && ! $column_blank ){
        if( ! $column_value){
          // Then it must be a normal column name

          if(stristr($column, '.')){
            list($table, $column) = static::getTableAliases($column);
            if($table){ $table .= '.'; }
          }
        }

        $column_quote = $column_value ? $quotes['value'] : $quotes['column'];

        if(static::hasUnescapedSingleQuote($column, $column_quote)){
          // Column already has single quote (suspicious!!!). Empty the array and terminate the loop
          $columns = [];
          break;
        }

        $column = $table . "{$column_quote}". $column ."{$column_quote}";
      }


      if($alias){
        $alias_quote = $quotes['column'];

        $alias = "{$alias_quote}". trim($alias) ."{$alias_quote}";

        $column = "$column as $alias";
      }

      $columns[ $index ] = $column;
      $column_types[ $index ] = $column_type;
    }

    return $is_array ? [$columns, $column_types] : [ $columns[0], $column_types[0] ];
  }

  /**
   * For Database queries: - Add single-quotes around values
   * @param array|string $values The values to add quotes to
   * @return array|string
   */
  public function add_quotes_Values($values){
    if( ! $is_array = is_array($values)){
      $values = [$values];
    }

    $quotes = ['column' => "`", 'value' => "'"];

    foreach($values as $key => $value){
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

      $values[ $key ] = $value;
    }

    return $is_array ? $values : $values[0];
  }


  /**
   * Strips trailing semicolon and returns the new value
   * @param string $value
   * @return string
   */
  private static function stripTrailingSemicolon($value) {
    return Str::stripTrailingChar($value, ';');
  }


  /**
   * Creates a relatively unique string (non-numeric) id
   * @param int $length         The length of the hashed string id
   * @param array $table_column An array containing the table and id column e.g ['users', 'user_id']
   * @return string
   */
  public function uniqueStringId(int $length = 9, array $table_column = []){
    $string = rand(1,9) * time();

    $id_group[] = $unique_id = Str::hash($string, $length);

    if($table_column){
      list($table, $column) = $this->only($table_column, ['table', 'column']);

      for($n = 1; $n < 15; $n++){
        $id_group[] = Str::hash($string, $length, $n);
      }

      $where = [
        $column => ['IN', $id_group]
      ];

      if($existing_rows = static::select($table, $column, $where)->all()){
        $existing_rows_id = array_column($existing_rows, $column);
        $id_group = Arr::pickExcept( array_flip($existing_rows_id), array_flip($id_group) );
      }

      $unique_id = $id_group ? $id_group[0] : null;
    }

    return $unique_id;
  }


  /**
   * Holds the name of a specific method that calls another more generic one
   */
  private static function caller() {
    $caller = static::$caller;
    return (static::$caller = '') ?: $caller;
  }

  /**
   * Returns the stored main query clause
   */
  public function getSql(){
    $sql = static::$sql;
    return (static::$sql = '') ?: $sql;
  }

  /**
   * Returns the stored 'OR' where clause
   */
  public function getOrWhere(){
    $orWhere = static::$orWhere;
    return (static::$orWhere = '') ?: $orWhere;
  }

  /**
   * Returns the stored where clause including the 'OR' where clause
   */
  public function getWhere(){
    $where = static::$where;
    return (static::$where = '') ?: $where .' '. $this->getOrWhere();
  }

  /**
   * Returns the stored query including the where clause
   */
  public function queryString() {
    return $this->getSql() .' '. $this->getWhere();
  }

  /**
   * Returns the MySQL method to call. One of ['query', 'multi_query']
   */
  public function sqlMethod() {
    $sqlMethod = static::$sqlMethod;
    return (static::$sqlMethod = '') ?: $sqlMethod;
  }

  /**
   * Begin a database transaction
   */
  public static function start_txn() {
    static::$sql = "START TRANSACTION";
    static::run_single();
  }

  /**
   * End a database transaction
   * @param bool $ok If true, commits the transaction, else, rolls back
   */
  public static function end_txn($ok) {
    static::$sql = ($ok === true) ? "COMMIT" : "ROLLBACK";
    static::run_single();
    static::close();
  }

  private function run(){
    static::$result = call_user_func(
      [$this->connection, $this->sqlMethod()], $sql = $this->queryString()
    );

    if ( ! static::$result){
      static::throwError(
        $this->connection->error."; Problem with Query \"". $sql ."\"\n"
      );
    }

    return $this;
  }

  /**
   * Executes the stored single query
   * @return $this
   */
  private static function run_single(){
    static::$multi = [];

    static::$sqlMethod = 'query';

    return (new static)->run();
  }

  /**
   * Executes the stored multiple queries
   * @return $this
   */
  private static function run_multi(){

    static::$sqlMethod = 'multi_query';

    return (new static)->run();
  }

  /**
   * Fetches the affected rows after single or multiple queries are executed
   * @return void
   */
  private function fetch(){
    $connection = $this->connection;

    $function = static::$assoc ? 'fetch_assoc' : 'fetch_object';

    if( ! static::$multi ){
      // Single Query

      if ( !empty(static::$result->num_rows)){
        while( $row = static::$result->$function() ){
          $fetch[] = $row;
        }
      }

    }
    else {
      // Multi Query
      static::$isNewQuery = false;

      $keys = ['queries', 'labels', 'use_result'];

      list(static::$sql, $labels, $use_result) = Utility::array_pick($keys, static::$multi, false, false);

      $nn = 1;
      do {
        if($nn > 7) dd($nn, $connection->error);

        if($connection->more_results() and $connection->next_result()){
          if($result = $connection->store_result()){
            $group = (isset($group)) ? ++$group : 0;

            if(!isset($fetch)){
              $fetch = $use_result ? [] : 0;
            }

            $key = !empty($labels) ? $labels[$group] : $group;

            while($row = $result->$function()){
              ($use_result) ? $fetch[ $key ][] = $row : $fetch = $group + 1;
            }

            $result->free();
          }
        }

//      } while( $connection->more_results() and $connection->next_result() );
      } while( $nn < 10 and $connection->error == '' and $connection->more_results() and $connection->next_result() );
    }

    if(isset($fetch)){
      static::$count = (is_array($fetch)) ? count(static::$rows = $fetch) : $fetch;
    }
    else {
      //  For operations like 'CREATE Table' which return (bool) FALSE on both 'Success' and 'Failure'
      static::$result = ($connection->error == '') ? true : null;
    }
//    dd(static::$sql, $labels ?? '', static::$count, static::$rows);
  }

  /**
   * Stores multiple queries for execution
   * @param array $queries    The queries to run
   * @param array $labels     The labels to use for the results of the queries
   * @param bool $use_result  If true, stores the query group rows, else, stores the query group count
   * @return $this
   */
  public static function multi_queries(array $queries, array $labels = [], bool $use_result = false){
    // Validate $queries : array of strings.


    static::$isNewQuery = true;

    $queries = Utility::stripEmpty($queries);
    $queries = array_map([static::class, 'stripTrailingSemicolon'], $queries);
    $queriesCount = count($queries);

    $labels = Utility::stripEmpty($labels);
    $labelsCount = count($labels);

    if($use_result and !empty($labels) and $queriesCount != $labelsCount){
      static::throwError(
        'Function run_multi(): Number of "Queries" combined in @param $queries' .
        ' must match Number of "Labels" provided in @param $labels'
      );
    }

    static::$sql = implode('; ', $queries);

    static::$multi = [
      'queries' => static::$sql, 'labels' => $labels, 'use_result' => $use_result
    ];

    return new static();
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

//    $valid = !( !$values_count or (!$query && empty($valid_keys) && empty($valid_items_count)) );
    $valid = !( (!$query && empty($valid_keys) && empty($valid_items_count)) );

    return $valid ? [$values, $query] : null;
  }


  private static function setQueryValues(array $values, string $query = ''){
    $valid_args = static::validateQueryValues($values, $query);

    if( ! $valid_args){
      $error = 'MysqlQuery::setQueryValues requires arguments:'
        . ' EITHER i.) (array $values e.g [$name, $email], string $query e.g "name = ?1 OR email = ?2")'
        . ' OR ii.) (array $values e.g ["query" => "name = ?1 OR email = ?2", "values" => [$name, $email]])';

      // ToDo: store error message in json format so after getMessage(), one can json_decode(..., true) or use as valid json
      /*$error = json_encode([
        'MysqlQuery::setQueryValues required arguments' => [
          'EITHER' => '(array $values, string $query)',
          'OR' => '(array $values) where $values structure is ["query" => $query, "values" => $values])'
        ]
      ]);*/

      if($caller = static::caller()){
        $error .= " called in ". static::class ."::{$caller}()";
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
    $value = $or_values = [];

    foreach($columns_values as $column => $value){
      // Track the Recursive calls that follow
      static::$recursive[] = 0;

      $keys = array_keys( static::$recursive );
      $current_depth = count( $keys );
      $current_turn = static::$recursive[ $current_depth - 1 ];

      foreach ($value as $c => $val){
        $next_val = is_numeric($c) ? $val : [$c => $val];
        $where_clause = '';

        if($next_val){
          static::$recursive[ $current_depth - 1 ] = ++$current_turn;

          $where_clause = static::whereAssoc($next_val)->getWhere();
          $where_clause = trim( str_replace('WHERE', '', $where_clause));
        }

        // Remove the original key. At the end, only the non-OR values will remain in $value, for count($value) eval
        unset( $value[ $c ] );

        if(is_numeric($c)){
          // If there is at least one non-OR value in $value, prepend this OR group with 'OR'
          $or_values[ "_oR_$c|b" ] = $where_clause;
        }
        else {
          $value[] = $where_clause;
        }
      }

      $column = "$column|b";
    }

//    return [$column, $value, $or_values];

    // Must be a processed OR group
    $value = Utility::stripEmpty($value);
    $or_values = Utility::stripEmpty($or_values);

    foreach($or_values as $i => $or_val){
      $or_values[ $i ] = '('. $or_val .')';
    }

    $or_values = implode(' OR ', $or_values);

    if($value){
      $or_values = ($or_values ? ' OR ' : '') . $or_values;
      $value = implode(' AND ', $value);
      $value = $value ? 'OR ('. $value . $or_values .')' : '';
    }

    static::$orWhere = $value;

    return new static();
  }


  public static function whereAssoc(array $columns_values){
    if( ! $columns_values){
      static::throwError('MysqlQuery::where() Example: ["name" => $name, "email" => $email], ["3|v" => ["!=", "status"]]');
    }

    $ops = [
      'equals' => ['=', '!=', '<', '<=', '>', '>='],
      'in' => ['IN','NOT IN'],
      'between' => ['BETWEEN','NOT BETWEEN'],
    ];
    $ops['equals_in'] = array_merge( $ops['equals'], $ops['in'] );

    $where_array = $or_values = [];

    foreach($columns_values as $column => $value){
      $join = 'AND';

      if(is_numeric($column) && is_array($value)){
        $join = '';
        $value = static::orWhere([$column => $value])->getOrWhere();
      }

      $operator = '=';

      $column_is_blank = static::isBlank($column);

      list($column, $column_type) = static::add_quotes_Columns($column);

      if(!$join or $column_is_blank){
        $column = $operator = '';
      }
      else if( ! is_array($value)){
        list($value) = static::add_quotes_Values([$value]);
      }
      else {
        $operator = strtoupper( trim( array_shift($value)));

        $value = Arr::unwrap($value);

        if( in_array($operator, $ops['between']) ){
          $value = $value[0] .' AND '. $value[1];
        }
        else if( in_array($operator, $ops['equals_in']) ){
          $value = static::add_quotes_Values($value);

          if(in_array($operator, $ops['in'])){
            $value = '(' . implode(',', $value) . ')';
          }
          if(in_array($operator, $ops['equals'])){
            $value = array_shift($value);
          }
        }
      }

      /*if(is_array($value) || $or_values){
        // Must be a processed OR group
        $value = Arr::stripEmpty($value);
        $or_values = Arr::stripEmpty($or_values);

        foreach($or_values as $i => $or_val){
          $or_values[ $i ] = '('. $or_val .')';
        }

        $or_values = implode(' OR ', $or_values);

        if($value){
          $or_values = ($or_values ? ' OR ' : '') . $or_values;
          $value = ($value = implode(' AND ', $value)) ? '('. $value . $or_values .')' : '';
        }
      }*/

      $where_array[] = (count($where_array) ? "$join " : '') . trim("$column $operator $value");
    }

    static::$where = ($where_array) ? "WHERE " . implode(' ', $where_array) : '';

    return new static;
  }

  public function limit( int $length, int $start = 0 ){
    static::$limit = " LIMIT {$start}, {$length}";
    return $this;
  }

  /**
   * Returns all rows with only the specified columns
   * @param array $rows           The array of rows as from a database fetch
   * @param array|string $columns The columns to pick. All other columns are dropped
   * @return array
   */
  public function only($rows, $columns){
    return Arr::each($rows, [Arr::class, 'pickOnly'], (array) $columns);
  }

  /**
   * Returns all rows with only the specified columns
   * @param array $rows
   * @param array|string $columns The columns to pick. All other columns are dropped
   * @return array
   */
  public function except(array $rows, $columns){
    return Arr::each($rows, [Arr::class, 'pickExcept'], (array) $columns);
  }


  public function asArray() {
    static::$assoc = true;
    return $this;
  }

  public function all(){
    if(static::$multi){
      if(static::$isNewQuery){
        static::run_multi()->fetch();
      }
    }
    else {
      static::run_single()->fetch();
    }

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

    list($columns) = static::add_quotes_Columns($columns, true);

    $columns = implode(',', $columns);

    if($where){
      static::$caller = 'select';
//      static::whereRaw( $where );
      static::whereAssoc( $where );
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

//    static::run();

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

//      static::run();

      if($result = $connection->affected_rows) {
        list($table, $columns) = Schema::get($table);
        return $this->getLastInsert($table, $columns)->first();
      }
    }

    return false;
  }


}

?>
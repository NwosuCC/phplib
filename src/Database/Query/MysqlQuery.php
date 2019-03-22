<?php

namespace Orcses\PhpLib\Database\Query;

use mysqli as MySQLi;
use Orcses\PhpLib\Database\Connection\MysqlConnection;
use Orcses\PhpLib\Incs\HandlesErrors;
use Orcses\PhpLib\Incs\HandlesError;
use Orcses\PhpLib\Interfaces\Connectible;
use Orcses\PhpLib\Utility\Str;
use Orcses\PhpLib\Utility\Arr;


class MysqlQuery extends Query implements HandlesErrors {

  use HandlesError;


  private static $error_handler;

  /**
   * @var MySQLi instance
   */
  protected $connection;

  private $table, $a_i_column;

  private $caller = '';

  private $sql,$tempSql, $where, $orWhere, $order, $limit, $doNotModify;

  private $sqlMethod, $multi = [], $isNewQuery = false, $assoc = false;

  public  $result, $rows = [], $count = 0, $dry_run = false, $prevSql;



  /**
   * Returns a new instance of this class. Especially for use within static functions
   *@return $this
   */
  protected static function newQuery() {
    // ToDo: ...
    return new static();
  }


  // See HandlesErrors::setErrorHandler() for more info
  public static function setErrorHandler(array $callback = []) {
    static::$error_handler = $callback;
  }


  // See HandlesErrors::getErrorHandler() for more info
  public static function getErrorHandler() {
    return static::$error_handler;
  }

  public function table(string $table = ''){
    if($table){
      $this->table = $table;
    }

    return $this->table;
  }

  private function attempt_A_I_ColumnFromTable(){
    if($columns = $this->getTableColumns( $this->table() )){

      if($columns = array_filter($columns, function($row){ return $row->Extra === 'auto_increment'; })){
        return array_shift($columns)->Field;
      }
    }

    return null;
  }

  public function autoIncrementColumn(string $a_i_column = ''){
    if($a_i_column){
      $this->a_i_column = $this->escape($a_i_column);
    }
    else if( ! $this->a_i_column){
      $this->a_i_column = $this->attempt_A_I_ColumnFromTable();
    }

    return $this->a_i_column;
  }

  // Can use Str::clean
  public static function sanitize(string $value){
    return stripslashes( htmlspecialchars( trim($value)));
  }

  /**
   * Prepares the query for execution. Escapes single- and double-quotes
   * @param array|string $values The values to escape
   * @return array
   */
  public function escape($values){
    if( ! $is_array = is_array($values)){
      $values = [$values];
    }

    $escaped_values = [];

    foreach($values as $key => $raw_value){
      if(is_array($raw_value)){
        $value = $this->escape($raw_value);
      }
      else{
        $value = static::sanitize($raw_value);

        // Escape if NOT marked as Raw MySQL Query using '|q'
        if(stripos($value, '|q') === false){
          $value = static::newQuery()->connection->real_escape_string($value);
        }
      }

      $escaped_values[ $key ] = $value;
    }

    return $is_array ? $escaped_values : $escaped_values[0];
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

  private static function isRawData(string $type, string $value){
    $types = ['c', 'q', 'v', 'b'];

    if(!in_array($type, $types)){
      static::throwError('MysqlQuery::isRawData() - invalid argument "type" ['. $type .'] supplied');
    }

    $marker = "|$type";

    return Str::hasTrailingChar($value, $marker);
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

//    return $is_array ? [$columns, $column_types] : [ $columns[0], $column_types[0] ];
    return $is_array ? $columns : $columns[0];
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
        // Then it must be an ordinary value

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
  public static function stripTrailingSemicolon($value) {
    return Str::stripTrailingChar($value, ';');
  }


  /**
   * Creates a relatively unique string (non-numeric) id
   * @param string $id_column   The name of the id_string column
   * @param int $chunk          How many IDs to generate and return. Max is 100
   * @param int $length         The length of the hashed string id
   * @return array
   */
  public function uniqueStringId($id_column, $chunk = 1, int $length = 16){
    $chunk = min($chunk,100);

    $string = rand(1,9) * rand(1,9) * time();

    $id_group[] = $unique_id = Str::hash($string, $length);

    // Twice the number of required IDs to cover for any existing ones
    $iterations = $chunk * 2;

    for($n = 1; $n < $iterations; $n++){
      $id_group[] = Str::hash($string, $length, $n);
    }

    $this->where([
      $id_column => ['IN', $id_group]
    ]);

    if($existing_rows = $this->select([$id_column])->asArray()->all()){

      $existing_rows_id = array_column($existing_rows, $id_column);

      $id_group = Arr::pickExcept( array_flip($existing_rows_id), array_flip($id_group) );
    }

    return $id_group ? array_slice($id_group, 0, $chunk) : null;
  }


  /**
   * Resets and returns the name of a specific method that calls another more generic one
   */
  private function caller() {
    $caller = $this->caller;

    return ($this->caller = '') ?: $caller;
  }

  /**
   * Resets and returns the stored 'OR' where clause
   */
  private function getOrWhere(){
    $orWhere = $this->orWhere;

    return ($this->orWhere = '') ?: $orWhere;
  }

  /**
   * Resets and returns the stored where clause including the 'OR' where clause
   */
  private function getWhere(){
    $where = $this->where;

    return ($this->where = '') ?: $where .' '. $this->getOrWhere();
  }

  /**
   * Resets and returns the stored order-by clause
   */
  private function getOrder(){
    $order = $this->order;

    return ($this->order = '') ?: $order;
  }

  /**
   * Resets and returns the stored limit clause
   */
  private function getLimit(){
    $limit = $this->limit;

    return ($this->limit = '') ?: $limit;
  }

  /**
   * Resets and returns whether or not to apply previously specified constraints. Default is false
   */
  private function doNotModify() {
    $doNotModify = $this->doNotModify;

    return ($this->doNotModify = false) ?: $doNotModify;
  }

  /**
   * Resets and returns whether or not to fetch results as array. Default is false
   */
  private function assoc() {
    $assoc = $this->assoc;

    return ($this->assoc = '') ?: $assoc;
  }

  /**
   * Resets and returns any stored intermittent sql.
   * E.g see insertOrUpdate()
   */
  private function getTempSql(){
    $tempSql = $this->tempSql;

    return ($this->tempSql = '') ?: $tempSql;
  }

  /**
   * Resets and returns the stored main query only
   */
  private function getSql(){
    $sql = $this->sql;

    return ($this->sql = '') ?: $sql;
  }

  /**
   * Returns the non-executed stored query including the where clause
   */
  public function queryString() {
    if($sql = $this->getSql() .' '. $this->getTempSql()){
      $this->prevSql = $sql;
    }

    return $sql;
  }

  /**
   * Returns the last executed sql
   */
  public function prevSql(){
    return $this->prevSql;
  }

  /**
   * Returns the MySQL method to call in the next execution. One of ['query', 'multi_query']
   */
  private function sqlMethod() {
    $sqlMethod = $this->sqlMethod;

    return ($this->sqlMethod = '') ?: $sqlMethod;
  }


  /**
   * Begin a database transaction
   */
  public function startTransaction() {
    $this->sql = "START TRANSACTION";

    $this->run();
  }

  /**
   * End a database transaction
   * If $this->commit() resolves to true, it commits the transaction, else, rolls back
   */
  public function endTransaction() {
    $this->sql = $this->commit() ? "COMMIT" : "ROLLBACK";

    $this->run();

    $this->connection->close();
  }

  private function commit(){
    $dry_run = $this->dry_run;

    return ($this->dry_run = false) ?: ! $dry_run;
  }

  public function dryRun(){
    $this->dry_run = true;

    return $this;
  }

  public function asTransaction($callable){
    $this->startTransaction();

    $result = call_user_func($callable, ...func_get_args());

    $this->endTransaction();

    return $result;
  }


  /**
   * Executes the stored query. Single query by default except another is explicitly set vie sqlMethod()
   * @return $this
   */
  private function run(){
    if( ! $method = $this->sqlMethod()){
      $method = 'query';
      $this->multi = [];
    }

    $this->result = call_user_func(
      [$this->connection, $method], $sql = $this->queryString()
    );

    if ( ! $this->result){
      static::throwError(
        $this->connection->error."; Problem with Query \"". $sql ."\"\n"
      );
    }

    return $this;
  }


  /**
   * Executes the stored multiple queries
   * @return $this
   */
  private function run_multi(){
    $this->sqlMethod = 'multi_query';

    return $this->run();
  }


  /**
   * Fetches the affected rows after single or multiple queries are executed
   * @return void
   */
  private function fetch(){
    $function = $this->assoc() ? 'fetch_assoc' : 'fetch_object';

    if( ! $this->multi ){
      // =============================================================================
      // Single Query Result
      // --------------------------------------------------------------------------
      $result_set = [];

      if ( !empty($this->result->num_rows)){
        while($row = call_user_func([$this->result, $function])){
          $result_set[] = $row;
        }
      }

    }
    else {
      // =============================================================================
      // Multi Query Result
      // --------------------------------------------------------------------------
      $this->isNewQuery = false;

      $connection = $this->connection;

      // Retrieve the multi-query parameters previously stored in multi_queries()
      $keys = ['queries', 'labels', 'use_result'];

      list($this->sql, $labels, $return_result) = Arr::pickOnly($this->multi, $keys, false);

      $queries = explode(';', $this->sql);
      $labels = array_reverse($labels);
      $count = count($queries);


      // Retrieve the result sets and store them in an array using the $labels as keys
      $result_set = $return_result ? [] : 0;

      do {
        $loop_index = (isset($loop_index)) ? ++$loop_index : 0;

        $label = !empty($labels) ? $labels[ $loop_index ] : $loop_index;

        $more_results = $connection->more_results();
        $next_result = $more_results ? $connection->next_result() : false;
        $stored_result = $more_results ? $connection->store_result() : false;

        $result_retrieved = ($stored_result or $next_result);


        if($more_results and $result_retrieved){

          if($stored_result){
            // Select Operation
            if($return_result){
              while($row = call_user_func([$stored_result, $function])){
                $result_set[ $label ][] = $row;
              }
            }
            else {
              $result_set[ $label ] = $connection->affected_rows;
            }

            $stored_result->free();
          }
          else if($connection->affected_rows >= 0 && $connection->field_count === 0){
            // Insert|Update Operation OR Create|Drop Database|Table, etc
            $result_set[ $label ] = $connection->affected_rows;
          }
          else {
            // Not Yet Unclassified
            $result_set[ $label ] = !$connection->error;
          }
        }

      } while(
        ($connection->more_results() and $result_retrieved and --$count > 0)
      );
    }

    if(isset($result_set)){
      $this->count = (is_array($result_set)) ? count($this->rows = $result_set) : $result_set;
    }
  }

  /**
   * Stores multiple queries for execution
   * @param array $queries    The queries to run
   * @param array $labels     The labels to use for the results of the queries
   * @param bool $use_result  If true, stores the query group rows, else, stores the query group count
   * @return $this
   */
  public function multi_queries(array $queries, array $labels = [], bool $use_result = false){
    // Validate $queries : array of strings.

    $this->isNewQuery = true;

    $queries = Arr::stripEmpty($queries);
    $queries = Arr::each($queries, [static::class, 'stripTrailingSemicolon']);
    $queriesCount = count($queries);

    $labels = Arr::stripEmpty($labels);
    $labelsCount = count($labels);

    if($use_result and !empty($labels) and $queriesCount != $labelsCount){
      static::throwError(
        'Function run_multi(): Number of "Queries" combined in @param $queries' .
        ' must match Number of "Labels" provided in @param $labels'
      );
    }

    $this->sql = implode('; ', $queries);

    $this->multi = [
      'queries' => $this->sql, 'labels' => $labels, 'use_result' => $use_result
    ];

    return $this;
  }


  private function validateQueryValues(array $values, string $query = ''){
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

    $valid = !( (!$query && empty($valid_keys) && empty($valid_items_count)) );

    return $valid ? [$values, $query] : null;
  }


  /**
   * @param array $values
   * @param string $query
   *
   * Arguments examples:
   * EITHER i.)  (array $values e.g [$name, $email], string $query e.g "name = ?1 OR email = ?2")
   * OR     ii.) (array $values e.g ["query" => "name = ?1 OR email = ?2", "values" => [$name, $email]])';
   *
   * @return string
   */
  private function setQueryValues(array $values, string $query = ''){
    $valid_args = $this->validateQueryValues($values, $query);

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

      if($caller = $this->caller()){
        $error .= " called in ". static::class ."::{$caller}()";
      }

      static::throwError($error);
    }


    list($values, $query) = $valid_args;

    foreach ($values as $number => $var){
      $pos_value = strpos($query, '?');
      $pos_query = strpos($query, '?q');
      $is_query = ($pos_query !== false and $pos_value === $pos_query);

      if( ! $is_query ){
        $var = $this->add_quotes_Values( $this->escape($var) );
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


  /**
   * @param array $values
   * @param string $query  See setQueryValues() for how param $query may be empty
   * @return string
   */
  public function rawQuery(array $values, string $query = ''){
    $this->caller = $this->caller() ?: 'rawQuery';

    return $this->setQueryValues($values, $query);
  }

  /**
   * @param array $values
   * @param string $query  See setQueryValues() for how param $query may be empty
   * @return $this
   */
  public function whereRaw(array $values, string $query = ''){
    $this->caller = $this->caller() ?: 'whereRaw';

    $this->where = 'WHERE ' . $this->setQueryValues($values, $query);

    return $this;
  }


  public function orWhere(array $columns_values){
    $value = $or_values = [];

    foreach($columns_values as $column => $value){

      foreach ($value as $c => $val){
        $next_val = is_numeric($c) ? $val : [$c => $val];

        $where_clause = '';

        if($next_val){
          $where_clause = $this->where($next_val)->getWhere();

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
    }

    // Must be a processed OR group
    $value = Arr::stripEmpty($value);
    $or_values = Arr::stripEmpty($or_values);

    foreach($or_values as $i => $or_val){
      $or_values[ $i ] = '('. $or_val .')';
    }

    $or_values = implode(' OR ', $or_values);

    if($value){
      $or_values = ($or_values ? ' OR ' : '') . $or_values;
      $value = implode(' AND ', $value);

      $value = $value ? 'OR ('. $value . $or_values .')' : '';
    }

    $this->orWhere = $value;

    return $this;
  }


  public function where(array $columns_values){
    if( ! $columns_values){
      static::throwError(
        'MysqlQuery::where() Example: ["name" => $name, "email" => $email], ["3|v" => ["!=", "status"]]'
      );
    }

    $ops = [
      'equals' => $equals = ['=', '!=', '<', '<=', '>', '>='],
      'in' => $in = ['IN','NOT IN'],
      'equals_in' => $equals_in = array_merge( $equals, $in),
      'between' => $between = ['BETWEEN','NOT BETWEEN'],
    ];

    $where_array = $or_values = [];


    foreach($columns_values as $column => $value){
      $join = 'AND';

      if(is_numeric($column) && is_array($value)){
        $join = '';
        $value = static::orWhere([$column => $value])->getOrWhere();
      }

      $operator = '=';

      $column_is_blank = static::isBlank($column);

      $column = $this->add_quotes_Columns($column);

      if(!$join or $column_is_blank){
        $column = $operator = '';

      }
      else if( ! is_array($value)){
        list($value) = $this->add_quotes_Values([$value]);

      }
      else {
        $operator = strtoupper( trim( array_shift($value)));

        $value = $this->add_quotes_Values( Arr::unwrap($value) );

        if( in_array($operator, $ops['between']) ){
          $value = $value[0] .' AND '. $value[1];
        }
        else if( in_array($operator, $ops['equals_in']) ){

          if(in_array($operator, $ops['in'])){
            $value = '(' . implode(',', $value) . ')';
          }
          if(in_array($operator, $ops['equals'])){
            $value = array_shift($value);
          }
        }
      }

      $join = count($where_array) ? "$join " : '';

      $where_array[] = $join . trim("$column $operator $value");
    }

    $this->where = ($where_array) ? "WHERE " . implode(' ', $where_array) : '';

    return $this;
  }


  public function orderBy( string $column, string $direction = 'ASC'){
    $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

    $this->order = " ORDER BY {$column} {$direction}";

    return $this;
  }


  public function limit( int $length, int $start = 0 ){
    $this->limit = " LIMIT {$start}, {$length}";

    return $this;
  }


  public function asArray() {
    $this->assoc = true;

    return $this;
  }

  public function all(){
    if($this->multi){
      if($this->isNewQuery){
        $this->run_multi()->fetch();
      }
    }
    else {
      $this->run()->fetch();
    }

    return $this->rows;
  }

  public function count() {
    return $this->multi ? count($this->all()) : $this->result->num_rows;
  }

  public function first(){
    return ($rows = $this->all()) ? reset($rows) : null;
  }

  public function last(){
    return ($rows = $this->all()) ? end($rows) : null;
  }


  public function lastInsertId(array $columns = []){
    if($a_i_column = $this->autoIncrementColumn()){

      $this->where([
        "$a_i_column" => 'LAST_INSERT_ID()|q'
      ]);

      return $this->select($columns)->last();
    }

    return null;
  }


  public function lastModifiedRow(array $columns){
    if($a_i_column = $this->autoIncrementColumn()){
      $columns[] = $a_i_column;

      $this->orderBy($a_i_column, 'desc');

      $this->limit(1);

      return $this->select($columns)->first();
    }

    return null;
  }


  public function getLastParams(array $columns){
    $lastInsertId = $lastRow = null;

    if($rows = $this->count()){
      $lastInsertId = $this->lastInsertId($columns);

      $lastRow = static::lastModifiedRow($columns);
    }

    return [
      'rows' => $rows, 'lastInsertId' => $lastInsertId, 'lastRow' => $lastRow
    ];
  }


  public function select(array $columns = []){
    $columns = empty($columns) ? ['*'] : $columns;

    $columns = $this->add_quotes_Columns( $this->escape($columns), true);

    $columns = implode(',', $columns);

    $composition = [
      'SELECT', $columns, 'FROM', $this->table(),
      $this->getWhere(), $this->getOrder(), $this->getLimit()
    ];

    $this->sql = implode(' ', $composition);

    return $this;
  }


  public function ifNotExists(array $where_values){
    $this->where($where_values);

    $this->doNotModify = $this->select()->count() > 0;

    return $this;
  }


  public function insertElseUpdate(array $columns, array $values, array $update_values){
    if( ! $update_values) {
      static::throwError('insertOrUpdate() requires parameter [Array $update_values]');
    }

    $all_update_values = [];

    foreach ($update_values as $column => $value){
      $value = $this->add_quotes_Values( $this->escape([$value]));
      $all_update_values[] = "$column = " . array_shift($value);
    }

    $all_update_values = implode(',', $all_update_values);

    $this->tempSql = " ON DUPLICATE KEY UPDATE {$all_update_values}";

    return $this->insert($columns, $values);
  }


  public function insert(array $columns, array $values = []){
    // If a previous constraint prevents this operation, abort. E.g, see insertUnique()
    if($this->doNotModify()){
      return false;
    }

    if(func_num_args() === 1){
      list($columns, $values) = [array_keys($columns), array_values($columns)];

      /*$columns_values = $columns;

      $columns = sort( array_keys($columns_values));

      ksort($columns_values);

      $values = array_values($columns_values);*/

      $values = [$values];
    }

    $columns = $this->add_quotes_Columns( $this->escape($columns) );

    $insert_columns = '(' . implode(',', $columns) . ')';

    $values = $this->escape($values);

    $insert_values = $rows = [];

    foreach ($values as $value){
      $value = $this->add_quotes_Values( $this->escape($value) );

      $insert_values[] = '(' . implode(',', $value) . ')';
    }

    $rows = implode(',', $insert_values);

    $composition = [
      'INSERT INTO', $this->table(), $insert_columns, 'VALUES', $rows
    ];

    $this->sql = implode(' ', $composition);

    if($this->run()->result){
      $result = $this->lastInsertId();
    }

    return ($this->result && !empty($result)) ? $result : $this->connection->affected_rows;
  }


  public function update(array $updates) {
    if($this->doNotModify()){
      return false;
    }

    $columns_values = [];

    foreach ($updates as $column => $value){
      $column = $this->add_quotes_Columns( $this->escape($column) );

      list($value) = $this->add_quotes_Values( $this->escape([$value]) );

      $columns_values[] = "$column = $value";
    }

    $a_i_column = $this->add_quotes_Columns( $this->autoIncrementColumn() );

    // To track and return the last updated row
    $columns_values[] = "$a_i_column = LAST_INSERT_ID($a_i_column)";

    $columns_values = implode(',', $columns_values);

    if( ! $where = $this->getWhere()){
      static::throwError("Please, define a 'WHERE...' clause for this operation.");
    }

    $composition = [
      'UPDATE', $table = $this->table(), 'SET', $columns_values, $where
    ];

    $this->sql = implode(' ', $composition);

    if($this->run()->result){
      $result = $this->lastInsertId();
    }

    return ($this->result && !empty($result)) ? $result : $this->connection->affected_rows;
  }


  public function delete() {
    if($this->doNotModify()){
      return false;
    }

    if( ! $where = $this->getWhere()){
      static::throwError("Please, define a 'WHERE...' clause for this operation.");
    }

    $composition = [
      'DELETE FROM', $table = $this->table(), $where
    ];

    $this->sql = implode(' ', $composition);

    $this->run();

    return $this->connection->affected_rows;
  }


  // ToDo: Use array $options to collect user-specified arguments
  public function load_data_file(
    $file_path, $fields_term = '', $fields_enclosed = '',
    $lines_term = '', $ignore_lines = '', $columns = '', $set_columns = ''
  )
  {
    if($fields_term !== '' || $fields_enclosed !== ''){

      $fields_term_enclosure[] = "FIELDS";

      if($fields_term !== ''){
        $fields_term_enclosure[] = "TERMINATED BY '{$fields_term}'";
      }

      if($fields_enclosed != ''){
        $fields_term_enclosure[] = "OPTIONALLY ENCLOSED BY '{$fields_enclosed}'";
      }

      $fields_term_enclosure = implode(' ', $fields_term_enclosure);
    }
    else{
      $fields_term_enclosure = '';
    }

    if($lines_term !== ''){
      $lines_term = " LINES TERMINATED BY '{$lines_term}'";
    }

    if($ignore_lines !== ''){
      $ignore_lines = " IGNORE {$ignore_lines} LINES";
    }

    $load_columns = ($columns !== '') ? "({$columns})" : '';

    if($set_columns !== ''){
      $set_columns = " SET {$set_columns}";
    }

//    $sql = "LOAD DATA LOCAL INFILE --local-infile=1 '{$file_path}' INTO TABLE {$table} {$fields_term_enclosure} {$lines_term} {$ignore_lines} {$loadCols} {$set_columns}";

    $composition = [
      'LOAD DATA LOCAL INFILE --local-infile=1', $file_path, 'INTO TABLE', $table = $this->table(),
      $fields_term_enclosure, $lines_term, $ignore_lines, $load_columns, $set_columns
    ];

    $this->sql = implode(' ', $composition);

    $this->run();

    if ($this->result){
      $columns = str_replace('@', '', $columns);

//      return $this->getLastParams($columns, $a_i_column);
      return $this->getLastParams($columns);
    }

    return false;
  }


  public function createDatabase(string $name){
    $name = $this->add_quotes_Columns($name);

    $this->sql = 'CREATE DATABASE ' . $name;

    return $this->run()->result;
  }


  public function createTable(string $name, array $column_definitions){
    $name = $this->add_quotes_Columns($name);

    // ToDo: can this be escaped without errors ??
    $column_definitions = $this->escape($column_definitions);

    $composition = [
      'CREATE TABLE', $name, $column_definitions
    ];

    $this->sql = implode(' ', $composition);

    return $this->run()->result;
  }


  public function tableExists(string $table, string $database = ''){
    list($table, $database) = $this->escape([$table, $database]);

    $this->sql = $database
      ? "SHOW TABLES FROM `$database` WHERE `Tables_in_$database` LIKE '$table';"
      : "SHOW TABLES LIKE '$table';";

    return $this->run()->count();
  }


  public function getTableColumns(string $table){
    $table = $this->add_quotes_Columns($table);

    $this->sql = 'DESCRIBE ' . $table;

    return $this->all();
  }


  public function truncate_table(string $table){
    $table = $this->add_quotes_Columns($table);

    $this->sql = 'TRUNCATE TABLE ' . $table;

    return $this->run()->result;
  }


}

?>
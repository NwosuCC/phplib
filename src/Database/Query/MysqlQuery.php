<?php

namespace Orcses\PhpLib\Database\Query;


use mysqli as MySQLi;
use Orcses\PhpLib\Logger;
use Orcses\PhpLib\Utility\Str;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Interfaces\Connectible;
use Orcses\PhpLib\Exceptions\Database\InvalidColumnPropertyException;


class MysqlQuery extends Query {

  /** @var MySQLi instance */
  protected $connection;

  protected $table, $a_i_column;

  protected $caller = '';

  protected $sql, $tempSql, $locked;

  protected $columns = [];

  protected $wheres = [], $where, $orWhere, $order, $limit;

  protected $sqlMethod, $multi = [], $isNewQuery = false, $assoc = false;

  public  $result, $selected_columns, $rows = [], $count = 0, $dry_run = false, $prevSql;


  /**
   * Returns a new instance of this class. Especially for use within static functions
   *@return $this
   */
  protected static function newQuery()
  {
    // ToDo: ...
    return static::query();
  }


  protected static function throwError(string $message, string $func_name = '')
  {
    throw new InvalidColumnPropertyException( $message, $func_name );
  }


  public function table(string $table = '')
  {
    if($table){
      $this->table = $table;
    }

    return $this->table;
  }


  private function attempt_A_I_ColumnFromTable()
  {
    if($columns = $this->getTableColumns( $this->table() )){

      if($columns = array_filter($columns, function($row){ return $row->Extra === 'auto_increment'; })){
        return array_shift($columns)->Field;
      }
    }

    return null;
  }


  public function autoIncrementColumn(string $a_i_column = '')
  {
    if($a_i_column){
      $this->a_i_column = $this->escape($a_i_column);
    }
    else if( ! $this->a_i_column){
      $this->a_i_column = $this->attempt_A_I_ColumnFromTable();
    }

    return $this->a_i_column;
  }


  // Can use Str::clean
  public static function sanitize(string $value)
  {
    return stripslashes( htmlspecialchars( trim($value)));
  }


  /**
   * Prepares the query for execution. Escapes single- and double-quotes
   * @param array|string $values The values to escape
   * @return array
   */
  public function escape($values)
  {
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
   *     given "1.sn", returns ['', 'sn']
   * @param string $column
   * @return array
   */
  private static function getTableAliases(string $column)
  {
    $parts = Str::splitByChar($column, '.');

    if(is_numeric($parts[0])){
      $parts[0] = '';
    }

    return count($parts) >= 2 ? $parts : Arr::pad($parts, -2, '');
  }


  /**
   * E.g given "w.status as withdrawal_status", returns ['w.status', 'withdrawal_status']
   * @param string $column
   * @return array
   */
  private static function getColumnAliases($column)
  {
    $aliases = Str::splitByChar($column, 'as');

    return Arr::pad($aliases, 2, '');
  }


  private static function isRawData(string $type, string $value = null)
  {
    if(is_null($value)){
      return false;
    }

    $types = ['a', 'c', 'o', 'q', 'v', 'b'];

    if( ! in_array($type, $types)){
      static::throwError(
        'Invalid argument "type" ['. $type .'] supplied', __FUNCTION__
      );
    }

    $marker = "|$type";

    return Str::hasTrailingChar($value, $marker);
  }


  private static function isTableColumn($value)
  {
    return static::isRawData('c', $value);
  }


  // $column is meant to be blank so only the corresponding value appears in the sql query
  private static function isBlank($value)
  {
    return static::isRawData('b', $value);
  }


  // $column is a valid MySQL query/sub-query => no quotes
  private static function isRawQuery($value)
  {
    return static::isRawData('q', $value);
  }


  // $column is a Value - should be single-quoted
  private static function isRawValue($value)
  {
    return static::isRawData('v', $value);
  }


  // $column is a is Logic Operator - 'isBlank' implicitly
  private static function isLogicOperator($value)
  {
    if(static::isRawData('a', $value)) {
      $logic_op = 'AND';
    }
    elseif(static::isRawData('o', $value)){
      $logic_op = 'OR';
    }

    return !empty($logic_op) ? $logic_op : false;
  }


  /**
   * For Database queries: - Either add single-quotes around values
   *                       - Or add back-quotes around columns
   * @param array|string $columns The columns or values to add quotes to
   * @param bool $select_mode If true, columns are checked for aliases e.g "u.id as user_id"
   * @return array|string
   */
  public function add_quotes_Columns($columns, bool $select_mode = false)
  {
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
        pr(['usr' => __FUNCTION__, '$column' => $column, '$alias' => $alias, 'isRawQuery' => static::isRawQuery($column)]);
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

        if(Str::hasUnescapedSingleQuote($column, $column_quote)){
          // Column1 already has single quote (suspicious!!!). Empty the array and terminate the loop
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
  public function add_quotes_Values($values)
  {
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
  public static function stripTrailingSemicolon($value)
  {
    return Str::stripTrailingChar($value, ';');
  }


  /**
   * Creates a relatively unique string (non-numeric) id
   * @param string $id_column   The name of the id_string column
   * @param int $chunk          How many IDs to generate and return. Max is 100
   * @param int $length         The length of the hashed string id
   * @return array
   */
  public function uniqueStringId($id_column, $chunk = 1, int $length = 16)
  {
    $chunk = min($chunk,100);

    $string = rand(1,9) * rand(1,9) * time();

    $id_group[] = $unique_id = Str::randomHash($string, $length);

    // Twice the number of required IDs to cover for any existing ones
    $iterations = $chunk * 2;

    for($n = 1; $n < $iterations; $n++){
      $id_group[] = Str::randomHash($string, $length, $n);
    }

    $this->where([
      $id_column => ['IN', $id_group]
    ]);
    pr(['usr' => __FUNCTION__, '$id_column' => $id_column, '$id_group' => $id_group]);

    if($existing_rows = $this->select([$id_column])->asArray()->all()){

      $existing_rows_id = array_column($existing_rows, $id_column);

      $id_group = Arr::pickExcept( array_flip($existing_rows_id), array_flip($id_group) );
    }

    return $id_group ? array_slice($id_group, 0, $chunk) : null;
  }


  /**
   * Resets and returns the name of a specific method that calls another more generic one
   */
  protected function caller()
  {
    $caller = $this->caller;

    return ($this->caller = '') ?: $caller;
  }


  /**
   * Returns the columns from the last select operation
   */
  public function selectedColumns()
  {
    return $this->selected_columns;
  }


  /**
   * Resets and returns the stored 'OR' where clause
   */
  public function getOrWhere()
  {
    $orWhere = $this->orWhere;

    return ($this->orWhere = '') ?: $orWhere;
  }


  /**
   * Resets and returns the stored where clause
   */
  public function getWhere()
  {
    $where = $this->where;

    return ($this->where = '') ?: $where;
  }

  /**
   * Returns the stored where clause for further in-code processing
   */
  public function getTmpWhere(){
    pr(['tmp' => __FUNCTION__, '$this->wheres Tmp' => $this->wheres, '$this->>where' => $this->where, '$this->orWhere' => $this->orWhere]);
    return $this->getWhereClause(false);
  }


  /**
   * Returns the stored where clause including the 'OR' where clause for sql execution
   * @param bool $final
   * @return string
   */
  public function getWhereClause(bool $final = true)
  {
//    pr(['usr' => __FUNCTION__, '$this->wheres 111' => $this->wheres, '$this->>where' => $this->where, '$this->orWhere' => $this->orWhere, '$final' => $final]);

    $where = $this->getWhere();

    $orWhere = $this->getOrWhere();

    $where = ($where || $orWhere) ? trim($where. ' ' . $orWhere) : '';

    if($final){
      if($orWhere){
        $this->wheres[] = $orWhere;
      }

      $this->wheres = array_unique( $this->wheres );

      $where = trim( $this->wheres ? implode(' ', $this->wheres) : $where );

      $where = $where ? 'WHERE ' . $where : '';
    }

//    pr(['usr' => __FUNCTION__, '$this->wheres 222' => $this->wheres, '$this->>where' => $this->where, '$this->orWhere' => $this->orWhere, '$where' => $where]);
    return trim($where);
  }


  /**
   * Resets and returns the stored order-by clause
   */
  protected function getOrder()
  {
    $order = $this->order;

    return ($this->order = '') ?: $order;
  }


  /**
   * Resets and returns the stored limit clause
   */
  protected function getLimit()
  {
    $limit = $this->limit;

    return ($this->limit = '') ?: $limit;
  }


  /**
   * Resets and returns whether or not to apply previously specified constraints. Default is false
   */
  protected function doNotModify()
  {
    $doNotModify = $this->locked;

    return ($this->locked = false) ?: $doNotModify;
  }


  /**
   * Resets and returns whether or not to fetch results as array. Default is false
   */
  protected function assoc()
  {
    $assoc = $this->assoc;

    return ($this->assoc = '') ?: $assoc;
  }


  /**
   * Resets and returns any stored intermittent sql.
   * E.g see insertOrUpdate()
   */
  protected function getTempSql()
  {
    $tempSql = $this->tempSql;

    return ($this->tempSql = '') ?: $tempSql;
  }


  /**
   * Resets and returns the stored main query only
   */
  protected function getSql()
  {
    $sql = $this->sql;

    return ($this->sql = '') ?: $sql;
  }


  /**
   * Returns the non-executed stored query including the where, orderBy clauses, etc for execution
   */
  protected function sql()
  {
    if($sql = $this->getSql() .' '. $this->getTempSql()){
      $this->prevSql = $sql;
    }

    return $sql;
  }


  /**
   * Returns the last executed sql
   */
  public function prevSql()
  {
    return $this->prevSql;
  }


  /**
   * Returns the current non-executed sql parts
   */
  public function currentVars()
  {
    return [
      'sql' => $this->sql,
      'tempSql' => $this->tempSql,
      'orWhere' => $this->orWhere,
      'where' => $this->where,
      'order' => $this->order,
      'limit' => $this->limit,
    ];
  }


  /**
   * Returns the MySQL method to call in the next execution. One of ['query', 'multi_query']
   */
  private function sqlMethod()
  {
    $sqlMethod = $this->sqlMethod;

    return ($this->sqlMethod = '') ?: $sqlMethod;
  }


  /**
   * Begin a database transaction
   */
  public function startTransaction()
  {
    $this->sql = "START TRANSACTION";

    $this->run();
  }


  /**
   * End a database transaction
   * If $this->commit() resolves to true, it commits the transaction, else, rolls back
   */
  public function endTransaction()
  {
    $this->sql = $this->commit() ? "COMMIT" : "ROLLBACK";

    $this->run();

//    $this->connection->close();
  }


  private function commit()
  {
    $dry_run = $this->dry_run;

    return ($this->dry_run = false) ?: ! $dry_run;
  }


  public function dryRun()
  {
    $this->dry_run = true;

    return $this;
  }


  public function asTransaction($callable)
  {
    $this->startTransaction();

    $result = call_user_func($callable, ...func_get_args());

    $this->endTransaction();

    return $result;
  }


  /**
   * Executes the stored query. Single query by default except another is explicitly set vie sqlMethod()
   * @return $this
   */
  private function run()
  {
    if( ! $method = $this->sqlMethod()){
      $method = 'query';
      $this->multi = [];
    }

    $this->result = call_user_func(
      [$this->connection, $method], $sql = $this->sql()
    );

    if(app()->config('database.log_queries')){
      Logger::log('sql', [$this->connection->affected_rows, $sql]);
    }

    pr(['usr' => __FUNCTION__, '$sql' => trim($sql),
      'affected_rows' => $this->connection->affected_rows,
      'field_count' => $this->connection->field_count,
      'num_rows' => $this->result->num_rows ?? '']);

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
  private function run_multi()
  {
    $this->sqlMethod = 'multi_query';

    return $this->run();
  }


  /**
   * Fetches the affected rows after single or multiple queries are executed
   * @return void
   */
  private function fetch()
  {
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

        $this->selected_columns = $result_set ? array_keys( (array) end($result_set) ) : [];
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
  public function multi_queries(array $queries, array $labels = [], bool $use_result = false)
  {
    // Validate $queries : array of strings.

    $this->isNewQuery = true;

    $queries = Arr::stripEmpty($queries);
    $queries = Arr::each($queries, [static::class, 'stripTrailingSemicolon']);
    $queriesCount = count($queries);

    $labels = Arr::stripEmpty($labels);
    $labelsCount = count($labels);

    if($use_result and !empty($labels) and $queriesCount != $labelsCount){
      static::throwError(
        'Number of "Queries" combined in @param $queries' .
        ' must match Number of "Labels" provided in @param $labels', __FUNCTION__
      );
    }

    $this->sql = implode('; ', $queries);

    $this->multi = [
      'queries' => $this->sql, 'labels' => $labels, 'use_result' => $use_result
    ];

    return $this;
  }


  private function validateQueryValues(array $values, string $query = '')
  {
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
  private function setQueryValues(array $values, string $query = '')
  {
    $valid_args = $this->validateQueryValues($values, $query);

    if( ! $valid_args){
      $error = 'requires arguments:'
        . ' EITHER i.) (array $values e.g [$name, $email], string $query e.g "name = ?1 OR email = ?2")'
        . ' OR ii.) (array $values e.g ["query" => "name = ?1 OR email = ?2", "values" => [$name, $email]])';

      // ToDo: store error message in json format so after getMessage(), one can json_decode(..., true) or use as valid json
      /*$error = json_encode([
        'required arguments' => [
          'EITHER' => '(array $values, string $query)',
          'OR' => '(array $values) where $values structure is ["query" => $query, "values" => $values])'
        ]
      ]);*/

      if($caller = $this->caller()){
        $error .= " called in ". static::class ."::{$caller}()";
      }

      static::throwError($error, __FUNCTION__);
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
          static::throwError(
            "Specified maximum reasonable iterations ($max_n) exceeded", __FUNCTION__
          );
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
  public function rawQuery(array $values, string $query = '')
  {
    $this->caller = $this->caller() ?: __FUNCTION__;

    return $this->setQueryValues($values, $query);
  }

  /**
   * @param array $values
   * @param string $query  See setQueryValues() for how param $query may be empty
   * @return $this
   */
  public function whereRaw(array $values, string $query = '')
  {
    $this->caller = $this->caller() ?: __FUNCTION__;

    $this->where = trim( $this->setQueryValues($values, $query));
    pr(['usr' => __FUNCTION__, 'where' => $this->where]);

    return $this;
  }


  public function orWhere($column, $operator = null, $value = null)
  {
    // getTmpWhere() can only be non-empty here iff where() has been previously called
    if($tmp_where = $this->getTmpWhere()){
      $this->wheres[] = $tmp_where;
    }

    $columns_values = $this->getWhereColumnValues($column, $operator, $value);

    $count = count($columns_values);

    $where_and = $where_or = [];

    foreach($columns_values as $column => $value){

//      if(is_numeric($column) && is_array($value)){
      if($join = static::isLogicOperator($column)){
        $where = $this->orWhere($value)->getOrWhere();

        pr(['lgc' => __FUNCTION__, 'OR recurse 000 $where' => $where, '$count' => $count, '$where_or' => $where_or]);
//        $where_or[] = Str::stripLeadingChar( $where, 'OR' );
        $where_or[] = trim( $join .' '. $where);
        pr(['lgc' => __FUNCTION__, 'OR recurse 111 $where' => $where, '$count' => $count, '$where_or' => $where_or]);
      }
      else {
        $where_and[] = trim( $this->where([$column => $value])->getTmpWhere());
      }
    }

//    $where_or = trim( implode(' OR ', $where_or));
//    $where_or = implode(' ', $where_or);

    pr(['lgc' => __FUNCTION__, 'OR initial $where_and' => $where_and, '$count' => $count, '$where_or' => $where_or, '$where' => $this->where, '$wheres' => $this->wheres]);

    if($where_and){
//      $where_or = $where_or ? ' OR ('. $where_or .')' : '';

      pr(['lgc' => __FUNCTION__, 'OR inside 000 $where_and' => $where_and, '$count' => $count, '$where_or' => $where_or, '$where' => $this->where, '$wheres' => $this->wheres]);

      if($where_and = trim( implode(' AND ', $where_and))){
        array_unshift($where_or, $where_and);
      }

//      $where_or = $where_and ? 'OR ('. $where_and . $where_or .')' : '';

//      $where_or = $where_and ? $where_and .' '. $where_or : '';
    }

    if($where_or){
      if( ! $where_and && $count > 1){
        $where_or[0] = Str::stripLeadingChar( $where_or[0], 'OR');
        $where_or[0] = Str::stripLeadingChar( $where_or[0], 'AND');
      }

      $where_or = implode(' ', $where_or);

      $or = $tmp_where ? 'OR ' : '';

      if($count > 1){
        $where_or = '('. $where_or .')';
      }

      $where_or = $or . $where_or;
      pr(['lgc' => __FUNCTION__, 'OR inside 111 $where_and' => $where_and, '$count' => $count, '$where_or' => $where_or, '$where' => $this->where, '$wheres' => $this->wheres]);
    }

    //    $where_or = implode(' ', $where_or);
    pr(['lgc' => __FUNCTION__, 'OR final $where_and' => $where_and, '$where_or' => $where_or]);

    $this->orWhere = $where_or;

    return $this;
  }


  public function andWhere($column, $operator = null, $value = null){
    if($where = $this->getTmpWhere()){
      $this->wheres[] = $where;
    }
    pr(['usr' => __FUNCTION__, '111 $where' => $where, '$this->wheres' => $this->wheres, '$this->>where' => $this->where]);

    $where = $this->where($column, $operator, $value)->getTmpWhere();

    $this->wheres[] = 'AND (' . trim($where) . ')';
    pr(['usr' => __FUNCTION__, '222 $where' => $where, '$this->wheres' => $this->wheres, '$this->>where' => $this->where]);

    return $this;
  }


  protected function nullClause(string $column, bool $not = false)
  {
    $column = $this->add_quotes_Columns($column);
    pr(['tmp' => __FUNCTION__, '$not' => $not, '$column' => $column, '$where' => $this->where, '$this->wheres' => $this->wheres]);

    $not = $not ? '!' : '';

    if( $this->wheres ) {
      $this->wheres[] = "AND {$not} isnull({$column})";
    }
    else{
      $this->where ? $this->where .= ' AND' : $this->where = '';

      $this->where .= " {$not} isnull({$column})";
    }
  }


  public function whereNull($column)
  {
    $this->nullClause( $column );
  }


  public function whereNotNull($column)
  {
    $this->nullClause( $column, true );
  }


  public function where($column, $operator = null, $value = null)
  {
    $this->where = $this->orWhere = null;

    $columns_values = $this->getWhereColumnValues($column, $operator, $value);

    if( ! $columns_values){
      static::throwError(
        'Example: ["name" => $name, "email" => $email], ["3|v" => ["!=", "status"]]', __FUNCTION__
      );
    }

    $where_and = $where_or = [];

    foreach($columns_values as $column => $value){

      if($join = static::isLogicOperator($column)){

        $where = $this->orWhere([$column => $value])->getOrWhere();

        $where_or[] = trim($where);

        pr(['lgc' => __FUNCTION__, 'aftr recurse $where' => $where, '$where_or' => $where_or]);
      }
      else {
        $column_is_blank = static::isBlank($column);

        $column = $this->add_quotes_Columns($column);

        if($column_is_blank){
          $column = $operator = '';
        }
        else {
          [$operator, $value] = $this->getOperatorValue( $value );
        }

        $where_and[] = ($where_and ? 'AND ' : '') . trim("$column $operator $value");
      }
    }

    pr(['lgc' => __FUNCTION__, 'where_and 00' => $where_and, '$where_or' => $where_or, '$this->where' => $this->where]);
    if($where_or){
      pr(['lgc' => __FUNCTION__, 'b4 strip OR $where_or' => $where_or]);
      if( ! $where_and){
        $where_or[0] = Str::stripLeadingChar( $where_or[0], 'OR');
        $where_or[0] = Str::stripLeadingChar( $where_or[0], 'AND');
      }
//
//      foreach($where_or as $join => $clauses){
//        $where_or[$join] = implode(" {$join} ", $where_or[$join]);
//      }
      pr(['lgc' => __FUNCTION__, 'b4 implode OR $where_or' => $where_or]);

//      $where_or = '(' . implode(' ', $where_or) . ')';
      $where_or = implode(' ', $where_or);
      pr(['lgc' => __FUNCTION__, 'aftr implode OR $where_or' => $where_or, '$where_and' => $where_and]);

//      $where_and[] = ($where_and ?  'OR ' : '') . $where_or;
      $where_and[] = $where_or;
      pr(['lgc' => __FUNCTION__, 'aftr join where_and 00' => $where_and, '$where_or' => $where_or]);
    }

    pr(['lgc' => __FUNCTION__, 'where_and 11' => $where_and, '$this->where' => $this->where]);

    $this->where = ($where_and) ? implode(' ', $where_and) : '';

    pr(['lgc' => __FUNCTION__, 'where_and 22' => $where_and, '$this->where' => $this->where]);
    pr(['usr' => __FUNCTION__, '$this->wheres' => $this->wheres, '$this->>where' => $this->where]);

    return $this;
  }


  protected function getWhereColumnValues($column, $operator = null, $value = null)
  {
    if(is_array($column)) {
      $columns_values = $column;
    }
    elseif(func_num_args() === 2){
      $columns_values = [$column => ($value = $operator)];
    }
    else {
      $columns_values = [ $column => [$operator, $value] ];
    }

    return $columns_values;
  }


  protected function getOperatorValue($value){
    $ops = [
      'equals' => $equals = ['=', '!=', '<', '<=', '>', '>='],
      'in' => $in = ['IN','NOT IN'],
      'equals_in' => $equals_in = array_merge( $equals, $in),
      'between' => $between = ['BETWEEN','NOT BETWEEN'],
    ];

    if( ! is_array($value)){
      $operator = '=';

      $value = $this->add_quotes_Values($value);
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

    return [$operator, $value];
  }


  public function orderBy( string $column, string $direction = 'ASC')
  {
    $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

    $this->order = " ORDER BY {$column} {$direction}";

    return $this;
  }


  public function limit( int $length, int $start = 0 )
  {
    $this->limit = " LIMIT {$start}, {$length}";

    return $this;
  }


  public function asArray()
  {
    $this->assoc = true;

    return $this;
  }


  public function all()
  {
    if($this->multi){
      if($this->isNewQuery){
        $this->compileSelect()->run_multi()->fetch();
      }
    }
    else {
      $this->compileSelect()->run()->fetch();
    }

    return $this->rows;
  }


  public function count()
  {
    return $this->multi ? count($this->all()) : $this->result->num_rows;
  }


  public function first()
  {
    return ($rows = $this->all()) ? reset($rows) : null;
  }


  public function last()
  {
    return ($rows = $this->all()) ? end($rows) : null;
  }


  public function lastInsertId(array $columns = [])
  {
    if($a_i_column = $this->autoIncrementColumn()){

      $this->where([
        "$a_i_column" => 'LAST_INSERT_ID()|q'
      ]);

      return $this->select($columns)->last();
    }

    return null;
  }


  public function lastModifiedRow(array $columns)
  {
    if($a_i_column = $this->autoIncrementColumn()){
      $columns[] = $a_i_column;

      $this->orderBy($a_i_column, 'desc');

      $this->limit(1);

      return $this->select($columns)->first();
    }

    return null;
  }


  public function getLastParams(array $columns)
  {
    $lastInsertId = $lastRow = null;

    if($rows = $this->count()){
      $lastInsertId = $this->lastInsertId($columns);

      $lastRow = static::lastModifiedRow($columns);
    }

    return [
      'rows' => $rows, 'lastInsertId' => $lastInsertId, 'lastRow' => $lastRow
    ];
  }


  protected function compileSelect()
  {
    pr(['usr' => __FUNCTION__, 'columns' => $this->columns]);
    $this->columns = implode(',', array_merge( ...$this->columns ) );

    pr(['usr' => __FUNCTION__, '$this->wheres' => $this->wheres, '$this->where' => $this->where, '$this->orWhere' => $this->orWhere,
      '$this->sql' => $this->sql, 'columns' => count($this->columns)]);

    $composition = [
      'SELECT', $this->columns, 'FROM', $this->table(),
      $this->getWhereClause(), $this->getOrder(), $this->getLimit()
    ];

    $this->sql = implode(' ', $composition);
    pr(['usr' => __FUNCTION__, '$this->wheres' => $this->wheres, '$this->where' => $this->where, '$this->orWhere' => $this->orWhere,
      '$this->sql' => $this->sql, 'columns' => count($this->columns)]);

    $this->columns = [];

    return $this;
  }


  public function sum(string $column)
  {
    $column = $this->add_quotes_Columns( $this->escape($column), true);

    return "SUM({$column})|q";
  }


  public function selectAs(string $column, string $alias)
  {
    return $this->select( ["{$column} as {$alias}"] );
  }


  public function select(array $columns = [])
  {
    pr(['usr' => __FUNCTION__, 'columns 000' => $columns]);

    if($this->columns && ! $columns){
      return $this;
    }

    $columns = empty($columns) ? ['*'] : $columns;

    $this->columns[] = $this->add_quotes_Columns( $this->escape($columns), true);
    pr(['usr' => __FUNCTION__, 'columns 111' => $columns, '$this->columns' => $this->columns]);

    return $this;
  }


  public function ifNotExists(array $where_values)
  {
    $this->where($where_values);

    $this->locked = $this->select()->count() > 0;

    return $this;
  }


  public function insertElseUpdate(array $columns, array $values, array $update_values)
  {
    if( ! $update_values) {
      static::throwError('requires parameter [Array $update_values]', __FUNCTION__);
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


  public function insert(array $columns, array $values = [])
  {
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


  public function update(array $updates)
  {
    if($this->doNotModify()){
      return false;
    }

    $columns_values = [];

    foreach ($updates as $column => $value){
      $column = $this->add_quotes_Columns( $this->escape($column) );

      list($value) = $this->add_quotes_Values( $this->escape([$value]) );

      $columns_values[] = "$column = $value";
    }

    // 'LAST_INSERT_ID' here tracks and returns the last updated row
    if($a_i_column = $this->autoIncrementColumn() ){

      $a_i_column = $this->add_quotes_Columns( $a_i_column );

      $columns_values[] = "$a_i_column = LAST_INSERT_ID($a_i_column)";
    }

    $columns_values = implode(',', $columns_values);

    if( ! $where = $this->getWhereClause()){
      static::throwError(
        "Please, define a 'WHERE...' clause for this operation.", __FUNCTION__
      );
    }

    $composition = [
      'UPDATE', $table = $this->table(), 'SET', $columns_values, $where
    ];

    $this->sql = implode(' ', $composition);
    pr(['usr' => __FUNCTION__, '$this->sql' => $this->sql]);

    if($this->run()->result){
      // ToDo: add this as an option in config.database
      // E.g "on_update" return ['1' => true|false, '2' => 'affected_rows', '3' => 'last_updated_row']

//      $result = $this->lastInsertId();
    }

//    return ($this->result && !empty($result)) ? $result : $this->connection->affected_rows;
    return !! $this->connection->affected_rows;
  }


  public function delete()
  {
    if($this->doNotModify()){
      return false;
    }

    if( ! $where = $this->getWhereClause()){
      static::throwError(
        "Please, define a 'WHERE...' clause for this operation.", __FUNCTION__
      );
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


  public function createDatabase(string $name)
  {
    $name = $this->add_quotes_Columns($name);

    $this->sql = 'CREATE DATABASE ' . $name;

    return $this->run()->result;
  }


  public function createTable(string $name, array $column_definitions)
  {
    $name = $this->add_quotes_Columns($name);

    // ToDo: can this be escaped without errors ??
    $column_definitions = $this->escape($column_definitions);

    $composition = [
      'CREATE TABLE', $name, $column_definitions
    ];

    $this->sql = implode(' ', $composition);

    return $this->run()->result;
  }


  public function tableExists(string $table, string $database = '')
  {
    if( ! $exists = $this->hasTable($table)){

      list($escaped_table_name, $database) = $this->escape([$table, $database]);

      $this->sql = $database
        ? "SHOW TABLES FROM `$database` WHERE `Tables_in_$database` LIKE '$escaped_table_name';"
        : "SHOW TABLES LIKE '$escaped_table_name';";

      if($exists = !! $this->run()->count()){
        $this->addTable( $table );
      }
    }

    return $exists;
  }


  public function getTableColumns(string $table)
  {
    if( ! $this->hasTableDefinition( $table )){

      $quoted_table_name = $this->add_quotes_Columns($table);

      $this->sql = 'DESCRIBE ' . $quoted_table_name;

      $this->run()->fetch();

      $this->addTableDefinition( $table, $this->rows );
    }

    return $this->getTableDefinition( $table );
  }


  public function truncateTable(string $table)
  {
    $table = $this->add_quotes_Columns($table);

    $this->sql = 'TRUNCATE TABLE ' . $table;

    return $this->run()->result;
  }


}

?>
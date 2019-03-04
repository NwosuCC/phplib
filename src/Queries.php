<?php

namespace Orcses\PhpLib;

use Exception;
use mysqli as MySQLi;


class Queries {
  private static $sql, $conn;
  private static $show_errors = true, $callback = [];
  public static $result, $rows = [];

  public static function hideErrors(array $callback = []) {
    static::$show_errors = false;

    if(is_callable($callback[0])){
      static::$callback = $callback;
    }
  }

  private static function throwError($message){
    // First, log error
    // ...

    if(static::$show_errors){
      throw new Exception($message);
    }
    else {
      if(static::$callback){
        try {
          list($block_function, $block_params) = static::$callback;

          return call_user_func_array($block_function, $block_params);
        }
        catch (Exception $e) {}
      }
    }

    exit();
  }


  public static final function connection($db = '') {
    if(empty(static::$conn)){
      self::check_parameters();

      $db = $db ?: DB_DATABASE;

      static::$conn = new MySQLi(DB_HOST,DB_USERNAME,DB_PASSWORD, $db);
    }

    return static::$conn;
  }

  public static function check_parameters() {
    $parameters = [
      'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'
    ];

    $missing = array_filter($parameters, function ($param){
      return !defined($param);
    });

    if($missing) {
      static::throwError(
        "Undefined Database parameters: " . implode(', ', $missing)
      );
    }
  }

  public static function close() {
    static::connection()->close();
  }

  public static function escape(Array $values){
    $escaped_values = [];
    foreach($values as $key => $value){
      if(is_array($value)){
        $escaped_values[$key] = static::escape($value);
      }else{
        $value = stripslashes(htmlspecialchars(trim($value)));
        $escaped_values[$key] = static::connection()->real_escape_string($value);
      }
    }
    return $escaped_values;
  }

  private function backQuoteName($name){
    $nameSpell = str_split($name);
    if(reset($nameSpell) == '`' AND end($nameSpell) == '`'){
      $name = "`".$name."`";
    }
    return $name;
  }

  // Add single-quotes around values or back-quotes around columns for Database queries
  // Defaults to 'single-quotes around values' if invalid $type is supplied
  public static function add_quotes(Array $array, string $type){
    $types = [
      'column' => "`", 'value' => "'"
    ];

    foreach($array as $key => $value){
      $char = $types[ $type ] ?? $types['value'];

      ///E.g w.status as withdrawal_status : $value - w.status, $alias - withdrawal_status
      list($value, $alias) = (is_array($value)) ? $value : [$value, ''];

      // Catch any existing single quotes that is NOT already escaped
      $quote_index = strpos($value, $char);
      $slash_index = strpos($value,"\\");

      if($quote_index !== false and ($quote_index - 1) !== $slash_index){
        $array = false;  break;
      }

      if($type === 'column'){
        if(stripos($value, '|v') !== false){
          $char = '';  // no quotes
          $value = str_replace('|v', '', $value);
        }
        else if(stristr($value, '.')){
          $char = '';  // no quotes
//                list($table, $value) = explode('.', $value);
        }

        if($alias){
          $value = "$value as $alias";
        }
      }

      $table = (!empty($table)) ? $table.'.' : '';

      $array[ $key ] = $table . "{$char}". trim($value) ."{$char}";
    }

    return $array;
  }

  public static function add_single_quotes(Array $array){
    return static::add_quotes($array, 'value');
  }

  public static function add_back_quotes(Array $array){
    return static::add_quotes($array, 'column');
  }

  /* Escape [Insert|Update values OR Where clause] and add single quotes around them
   * Any value that has the '|s' (skip) or '|q' (query) marker will NOT be quoted, e.g now()
   * The '|q' (query) marker indicates a sub-query with User inputs that need to be escaped.
   * If no User inputs, the sub-query can pass with the '|s' marker only, just as any ordinary value.
   */
  private static function prepare($op, $columns_values){
    if(!in_array($op, ['i','u','w'])){ return null; }

    $columns = $values = $update_values = $where = [];
    foreach($columns_values as $column => $value){
      if(stripos($column, '|q') !== false){
        // Value is intended to form a MySql sub-Query
        $column = str_replace('|q', '', $column);
        list($value, $params) = $value;
        $s = substr_count($value, ':?');   $c = count($params);
        if($s !== $c){
          $count = ($c > $s) ? 'Excess' : 'Insufficient';
          die("$count parameters supplied for sub-query: '$value'");
        }
        foreach ($params as $param){
          list($param) = Queries::escape([$param]);
          $value = preg_replace('/\:\?/', $param, $value, 1);
        }
      }elseif(stripos($column, '|s') === false){
        if(!is_array($value)){ list($value) = Queries::escape([$value]); }
      }

      if(!is_array($value) or $op === 'w'){
        if(stripos($column, '||') === false){
          if(stripos($column, '|s') !== false){
            // Value is a MySql keyword|function e.g now(), so, should not be quoted
            $column = str_replace('|s', '', $column);
          }else{
            if(!is_array($value)){
              list($value) = static::add_single_quotes([$value]);
            }
          }
        }

        if($op === 'w' and is_array($value)){
          $equals = ['=', '<', '<=', '>', '>='];
          list($preposition, $vars) = $value;
          if(is_array($vars)){
            if(in_array($preposition, ['BETWEEN','NOT BETWEEN'])){
              $vars = "{$vars[0]} AND {$vars[1]}";
            }elseif(in_array($preposition, array_merge(['IN','NOT IN'], $equals))){
              $vars = array_values($vars);
              if(in_array($preposition, ['IN','NOT IN'])){
                $vars = static::add_single_quotes($vars);
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
                  list($item) = static::add_single_quotes([$item]);
                }
              }

              list($col) = static::add_back_quotes([$col]);
              $val[] = "$col $preposition $item";
            }
            $update_values[] = '('. implode(' OR ', $val) . ')';
          }
          else{
            if(in_array($preposition, $equals)){  // ['=', '<', '<=', '>', '>=']
              if(stripos($column, '|s') !== false){
                $column = str_replace('|s', '', $column);
              }else{
                list($vars) = static::add_single_quotes([$vars]);
              }
            }

            list($column) = static::add_back_quotes([$column]);
            $update_values[] = "$column $preposition $vars";
          }
        }else{
          list($column) = static::add_back_quotes([$column]);

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

    $columns = implode(',', $columns);   $values = implode(',', $values);
    $where = "WHERE " . implode(' AND ', $update_values);
    $update_values = implode(',', $update_values);
    $output = [
      'i' => [$columns, $values], 'u' => $update_values, 'w' => $where
    ];
    return $output[$op];
  }

  public static function start_txn() {
    static::run("START TRANSACTION");
  }

  private static function run($sql){
    static::$sql = $sql;
    static::$result = static::connection()->query(static::$sql);

    if (static::$result){
      return static::$result;
    }
    else {
      static::throwError(
        static::connection()->error."; Problem with Query \"".static::$sql."\"\n"
      );
    }
  }

  public static function run_multi($queries, $labels = '', $use_result = false){
    if(!is_array($queries)){ $queries = explode(';', $queries); }
    if(trim(end($queries)) == ''){ array_pop($queries); }
    $queriesCount = count($queries);

    if(!is_array($labels)){ $labels = explode(',', $labels); }
    if(trim(end($labels)) == ''){ array_pop($labels); }
    $labelsCount = count($labels);

    if($use_result and !empty($labels) and $queriesCount != $labelsCount){
      die('Function run_multi(): Number of "Queries" combined in @param $queries' .
        ' must match Number of "Labels" provided in @param $labels');
    }

    static::$sql = implode(';', $queries);
    $conn = static::connection();

    /*static::$sql = "SELECT * FROM settings WHERE name = 'withdrawal' AND sub1 = 'fee' AND sub2 = 'percent' ;
SELECT * FROM settings WHERE name = 'withdrawal' AND sub1 = 'fee' AND sub2 = 'amount' ;
SELECT * FROM settings WHERE name = 'withdrawal' AND sub1 = 'limit' AND sub2 = 'minimum' ;
SELECT * FROM settings WHERE name = 'withdrawal' AND sub1 = 'limit' AND sub2 = 'maximum';";*/

    if($conn->multi_query(static::$sql)){
      do{
        if(static::$result = $conn->store_result()){
          $group = (isset($group)) ? ++$group : 0;
          $row_array = [];
          while($row = static::$result->fetch_assoc()){
            if($use_result){
              $row_array[] = $row;
              if(!empty($labels)){
                $fetch[$labels[$group]] = $row_array;
              }else{
                $fetch[] = $row_array;
              }
            }else{
              $fetch = $group + 1;
            }
          }
          if(!isset($fetch)){ $fetch = false; }
          static::$result->free();
        }
      } while($conn->more_results() and $conn->next_result());

      if(isset($fetch)){
        return $fetch;
      }else{
        //  For operations like 'CREATE Table' which return (bool) FALSE on both 'Success' and 'Failure'
        return ($conn->error == '') ? true : null;
      }
    }else{
      die($conn->error."; Problem with Query \"".static::$sql."\"\n");
    }
  }

  public static function end_txn($ok) {
    static::run(($ok === true) ? "COMMIT" : "ROLLBACK");
    static::close();
  }

  public static function getLastParams($table,$columns,$a_i_column,$where=''){
    $rows = static::connection()->affected_rows;
    $lastInsertID = $lastRow = null;
    if($rows){
      $lastInsertID = static::select_lastInsertID($table,$columns,$a_i_column,$where);
    }
    if($a_i_column){
      $lastRow = static::select_lastRow($table,$columns,$a_i_column,$where);
    }
    return ['rows' => $rows, 'lastInsertID' => $lastInsertID[0], 'lastRow' => $lastRow[0]];
  }

  public static function select_lastInsertID($table,$columns, $a_i_column,$where=''){
    $columns = "$a_i_column, ".$columns;
    if(!$where){ $where = "WHERE "; }else{ $where .= " AND "; }
    $where .= " $a_i_column = LAST_INSERT_ID()";
    $limit = "LIMIT 1";
    return static::select($table, $columns, $where, '', $limit)->first();
  }

  public static function select_lastRow($table,$columns, $a_i_column,$where=''){
    $order = "ORDER BY {$a_i_column} DESC";
    $limit = "LIMIT 1";
    $columns = "$a_i_column, ".$columns;
    return static::select($table, $columns, $where, $order, $limit)->first();
  }

  public static function insert($table, $columns, $values, $a_i_column='', $update_values=''){
    if(is_array($columns)){ $columns = implode(',', $columns); }
    if(is_array($values)){ $values = implode(',', $values); }

    if(substr($values,0,1) == '('){
      $sql = "INSERT INTO {$table} ({$columns}) VALUES {$values}";
    }else{
      $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$values})";
    }

    if($update_values){
      $sql .= "ON DUPLICATE KEY UPDATE {$update_values}";
    }

    static::run($sql);
    return (!$a_i_column) ? static::$result : static::getLastParams($table, $columns, $a_i_column);
  }

  public static function insert_check($table, $columns, $values, $where = '', $a_i_column = ''){
    if(empty($where)){ die("Function insert_check() requires a 'WHERE...' clause"); }
    //$where .= "OR 0 = (SELEcT 1 FROM $table)";
    static::select($table,$columns,$where);
    if(!static::$result->num_rows) {
      return static::insert($table, $columns, $values, $a_i_column);
    }
    return FALSE;
  }

  public static function insert_new($table, $columns_values, $a_i_column = '', $update_values = []){
    if(!is_array($columns_values)){
      die('Function insert() expects 2nd parameter to be Associative Array $columns_values');
    }

    $first_element = $columns_values[array_keys($columns_values)[0]];
    if(!is_array($first_element)){
      list($columns, $values) = static::prepare('i', $columns_values);
    }else{
      $columns = $values = $final_values = [];
      foreach ($columns_values as $i => $column_value){
        list($columns, $values) = static::prepare('i', $column_value);
        $final_values[$i] = '(' . $values . ')';
      }
      $values = implode(',', $final_values);
    }

    if(substr($values,0,1) == '('){
      $sql_string = "INSERT INTO {$table} ({$columns}) VALUES {$values}";
    }else{
      $sql_string = "INSERT INTO {$table} ({$columns}) VALUES ({$values})";
    }
    if($update_values){
      $update_values = static::prepare('u', $update_values);
      $sql_string .= ($update_values) ? " ON DUPLICATE KEY UPDATE {$update_values}" : '';
    }

    static::run($sql_string);
    return (!$a_i_column) ? static::$result : static::getLastParams($table, $columns, $a_i_column);
  }

  public static function insert_check_new($table, $columns_values, $where, $a_i_column = ''){
    static::select($table, '', $where);

    if(!static::$result->num_rows){
      return static::insert_new($table, $columns_values, $a_i_column);
    }

    return FALSE;
  }

  public function insert_select($tables,$columns,$where='', $a_i_column='',$truncate_tmp=FALSE){
    if(!is_array($tables)){ die("function 'insert_select()' requires two(2) tables in an array."); }
    list($tableA,$tableB) = $tables;
    if(is_array($tableB)){ list($tableB,$tableC) = $tableB; }
    if(is_array($columns)){ list($columnsA,$columnsB) = $columns; }else{ $columnsA = $columnsB = $columns; }
    $sql = "INSERT INTO {$tableA} ({$columnsA}) SELECT {$columnsB} FROM {$tableB} {$where}";
    if(!empty($tableC)){ $sql .= " UNION SELECT {$columnsB} FROM {$tableC} {$where}"; }
    if($ins = $this->run($sql)){
      $result = ($a_i_column) ? $this->getLastParams($tableA, $columnsA, $a_i_column) : $ins;
      if(is_array($truncate_tmp) and $truncate_tmp[0] === TRUE){ $this->trunc_table($truncate_tmp[1]); }
      return $result;
    }
    return FALSE;
  }

  public static function select($table, $columns = '', $where = '', $order_by = '', $limit = ''){
    $columns = empty($columns) ? ['*'] : $columns;

    if(!is_array($columns)){
      $columns = explode(',', $columns);
    }

    $columns = implode(',', static::add_back_quotes($columns) );

    if(is_array($where)){
      $where = static::prepare('w', $where);
    }

    static::run("SELECT {$columns} FROM {$table} {$where} {$order_by} {$limit}");

    return new Queries;
  }

  public static function select_union($tables_columns = []){
    if(!is_array($tables_columns)){ $tables_columns = [$tables_columns]; }
    $sql_string = [];

    foreach ($tables_columns as $i => $vars){
      $args_length = count($vars);
      while ($args_length < 5){ $vars[] = '';  $args_length++; }
      list($table, $columns, $where, $order_by, $limit) = $vars;

      $columns = (empty($columns)) ? '*'
        : (is_array($columns)) ? implode(',', $columns) : $columns;
      (is_array($where)) ? $where = static::prepare('w', $where) : null;
      $sql_string[] = "SELECT {$columns} FROM {$table} {$where} {$order_by} {$limit}";
    }

    $sql_string = implode(' UNION ', $sql_string);
    static::run($sql_string);
    return new Queries;
  }

  public static function to_array() {
    static::$rows = [];
    if (static::$result->num_rows){
      while($row = static::$result->fetch_assoc()){ static::$rows[] = $row; }
    }
    return static::$rows;
  }

  public static function count() {
    return static::$result->num_rows;
  }

  public static function first() {
    static::to_array();
    return (count(static::$rows)) ? static::$rows[0] : [];
  }

  public static function last() {
    static::to_array();
    return (count(static::$rows)) ? end(static::$rows) : [];
  }

  public static function pluck($column) {
    static::to_array();
    return (count(static::$rows)) ? end(static::$rows) : [];
  }

  public static function update($table, $update_values, $where, $limit = '') {
    if(empty($where)){
      die("Function 'update()' requires a 'where' clause.");
    }
    if(is_array($update_values)){
      $update_values = implode(',', $update_values);
    }
    static::run("UPDATE {$table} SET {$update_values} {$where} {$limit}");
    return static::connection()->affected_rows;
  }

  public static function update_new($table, $update_values, $where, $limit = '', $return_sql = false) {
    if(empty($where)){
      die("Function 'update()' requires a 'where' clause.");
    }

    $update_values = static::prepare('u', $update_values);

    if(is_array($where)){
      $where = static::prepare('w', $where);
    }

    $sql_string = "UPDATE {$table} SET {$update_values} {$where} {$limit}";

    if($return_sql === true){
      return $sql_string;
    }

    static::run($sql_string);
    return static::connection()->affected_rows;
  }

  public static function update_bulk($table, $update_wheres) {
    $update_sql = '';   $update_result = 0;
    foreach($update_wheres as $up_w){
      $update_values = $up_w['values'];  $where = $up_w['where'];
      $update_sql[] = static::update_new($table, $update_values, $where, '', true);
    }
    if($update_sql){ $update_result = static::run_multi($update_sql); }
    return $update_result;
  }

  public function _delete($table,$where) {
    if(empty($where)){ die("crumb(): Please, define a 'where' clause for this operation."); }
    $sqdel = $this->run("DELETE FROM {$table} {$where}");
    if ($sqdel){ return $this->conn->affected_rows; }else{ return FALSE; }
  }

  public function loadDataFile($table,$filePath,$fieldsTerm='',$fieldsEnclosed='',$linesTerm='',$ignoreLines='',$columns='',$setCols='',$a_i_column='') {
    if($fieldsTerm != '' || $fieldsEnclosed != ''){
      $fieldsTermEncl  = " FIELDS ";
      if($fieldsTerm != ''){
        $fieldsTermEncl .= " TERMINATED BY '{$fieldsTerm}'";
      }
      if($fieldsEnclosed != ''){
        $fieldsTermEncl .= " OPTIONALLY ENCLOSED BY '{$fieldsEnclosed}'";
      }
    }else{
      $fieldsTermEncl = '';
    }
    if($linesTerm != ''){   $linesTerm   = " LINES TERMINATED BY '{$linesTerm}'"; }
    if($ignoreLines != ''){ $ignoreLines = " IGNORE {$ignoreLines} LINES"; }
    if($columns != ''){ $loadCols = "({$columns})"; }
    if($setCols != ''){ $setCols = " SET {$setCols}"; }

    $sql = "LOAD DATA LOCAL INFILE --local-infile=1 '{$filePath}' INTO TABLE {$table} {$fieldsTermEncl} {$linesTerm} {$ignoreLines} {$loadCols} {$setCols}";
    if ($this->run($sql)){
      $columns = str_replace('@', '', $columns);
      return $this->getLastParams($table, $columns, $a_i_column);
    }else{ return FALSE; }
  }

  public function crt_db($dbname){
    $dbname = $this->backQuoteName($dbname);
    $crdb = $this->run("CREATE database {$dbname}");
    return $crdb;
  }

  public function crt_table($table,$coldeftns){
    $table = $this->backQuoteName($table);
    $crtb = $this->run("CREATE TABLE {$table} {$coldeftns}");
    return $crtb;
  }

  public function trunc_table($table){
    return $this->run("TRUNCATE TABLE {$table}");
  }

}

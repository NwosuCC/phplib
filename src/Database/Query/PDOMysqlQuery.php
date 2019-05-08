<?php

namespace Orcses\PhpLib\Database\Query;


use Closure;
use PDO;
use PDOException;
use PDOStatement;


class PDOMysqlQuery extends PDOQuery
{

  /** @var PDO $connection */
  protected $connection;

  protected $table;

  protected $columns = [];

  protected $result_set = [];

  protected $count = 0;

  protected $fetch_mode;

  protected $sql;

  /** @var PDOStatement $statement */
  protected $statement;

  protected $affected_rows;


  /** @return  PDO */
  protected function getConnection()
  {
    return $this->connection;
  }


  protected function throwPDOException(string $message = null)
  {
    throw new PDOException(
      $message ?: $this->getConnection()->errorInfo()[2]
    );
  }


  public function setStatement(PDOStatement $statement)
  {
    $this->statement = $statement;

    return $this;
  }


  /**
   * @return  PDOStatement
   */
  public function getStatement()
  {
    return $this->statement;
  }


  public function setTable(string $table)
  {
    $this->table = $table;

    return $this;
  }


  public function getTable()
  {
    return $this->table;
  }


  protected function setSql(string $sql)
  {
    $this->sql = $sql;

    return $this;
  }


  protected function getSql()
  {
    $sql = $this->sql;

    return ($this->sql = '') ?: $sql;
  }


  protected function runTransaction(Closure $procedure)
  {
    $connection = $this->getConnection();

    try {
      $connection->beginTransaction();

      $procedure->call($this);

      $connection->commit();
    }
    catch (PDOException $exception){
      $connection->rollBack();
      throw $exception;
    }
  }


  protected function prepare(string $query)
  {
    $this->setStatement( $this->getConnection()->prepare($query) );

    return $this;
  }


  protected function bindParameters(array $bind_values)
  {
    extract($bind_values);

    foreach ($bind_values as $key => $value){
      $this->statement->bindParam( ":$key", $$key );
//      $this->statement->bindValue( ":$key", $value );
    }
    pr(['usr' => __FUNCTION__, '$bind_values' => $bind_values, '$key' => $key??'', '$value' => $value??""]);

    return $this;
  }


  protected function isAffectingStatement()
  {
//    return true
  }


  // Short for prepare => bind => exec
  public function exec(string $query = null, array $bind_values = null)
  {
    $query = 'SELECT * FROM users WHERE phone = :phone AND (id = :id OR sex = :sex)';
    $bind_values = ['phone' => '2347146546808', 'id' => '1', 'sex' => 'male'];
//    $query = 'SELECT * FROM users WHERE phone = :phone';
//    $bind_values = ['phone' => '2347146546808'];

//    $query = 'SELECT * FROM users WHERE phone = ?1 AND (center = ?2 OR state = ?3)';
//    $bind_values = [1 => 'auspice', 2 => '13A4', 3 => 'engine=3D'];
//    $bind_values = ['auspice', '13A4', 'engine=3D'];

//    if($query){
//      $this->prepare( $query ); //->bindParameters( $bind_values ?: [] );
//    }

    $this->statement = $this->getConnection()->prepare($query);

    $this->statement->execute($bind_values ?: []);

    pr(['usr' => __FUNCTION__, '$query' => trim($query),
      'statement' => $this->statement->queryString,
      'errorInfo' => $this->statement->errorInfo()
    ]);

    $result = $this->statement->fetchAll(PDO::FETCH_ASSOC);

    if($this->isAffectingStatement()){
      $this->affected_rows = $this->getStatement()->rowCount();
    }

    pr(['usr' => __FUNCTION__,
      'affected_rows' => $this->getAffectedRows(),
      '$result' =>$result,
      'statement' => $this->statement->queryString,
      'errorInfo' => $this->statement->errorInfo()
    ]);

    return $this;
  }


  protected function getAffectedRows()
  {
    return $this->affected_rows;
  }


  // Selection Query
  protected function query()
  {
    if( ! $statement = $this->getConnection()->query($this->getSql())){
      $this->throwPDOException();
    }

    $this->setStatement($statement);

    // --test
    $columnMeta = $this->getStatement()->getColumnMeta(0);
    pr([
      'usr' => __FUNCTION__,
      'Column table' => $columnMeta['table'],
      'Column name' => $columnMeta['name'],
      'Column length' => $columnMeta['len'],
      'Column flags' => $columnMeta['flags'],
    ]);

    return $this;
  }


  public function unprepared(string $sql)
  {
    $this->setSql( $sql )->query();

    return $this;
  }


  public function setFetchMode(int $fetch_mode)
  {
    // validate against PDO Params
    // Default: PDO::FETCH_ASSOC, PDO::FETCH_OBJ, PDO::FETCH_NUM
    // Explore: PDO::FETCH_COLUMN, PDO::FETCH_KEY_PAIR, PDO::FETCH_LAZY, PDO::FETCH_UNIQUE

    $this->fetch_mode = $fetch_mode;
  }


  public function getFetchMode()
  {
    return $this->fetch_mode ?: PDO::FETCH_ASSOC;
  }


  protected function fetch()
  {
    $this->result_set = $this->getStatement()->fetchAll( $this->getFetchMode() );

    $this->columns = $this->result_set ? array_keys( (array) end($result_set) ) : [];

    $this->count = (is_array($this->result_set)) ? count($this->result_set) : 0;
  }


  public function getResultSet()
  {
    return $this->result_set;
  }


  public function all()
  {
    $this->fetch();

    return $this->getResultSet();
  }


  public function first()
  {
    return reset( $this->all() );
  }


  public function last()
  {
    return end( $this->all() );
  }


  public function count()
  {
    return $this->isAffectingStatement() ? $this->getAffectedRows() : count( $this->all() );
  }


  /**
   * @param string $name
   * @param string[]  $columns     An indexed array of the columns definitions
   * @param string[]  $properties  An indexed array of the table properties definitions
   * @return static
   */
  public function createTable(string $name, array $columns, array $properties = null)
  {
    $columns = implode(', ', $columns);

    $properties = implode(', ', $properties);

    $parts = ['CREATE TABLE', ':name', '(:columns)', ':properties'];

    return $this->exec(
      implode(' ', $parts), compact('name', 'columns', 'properties')
    );
  }


  public function getTableColumns(string $table)
  {
    if( ! $this->hasTableDefinition( $table )){

      $quoted_table_name = $this->add_quotes_Columns($table);

      $this->sql = 'DESCRIBE ' . $quoted_table_name;

      $this->exec()->fetch();

      $this->addTableDefinition( $table, $this->rows );
    }

    return $this->getTableDefinition( $table );
  }


  public function truncateTable(string $table)
  {
    return $this->exec(
      'TRUNCATE TABLE :table', compact('table')
    );
  }


}

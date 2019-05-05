<?php

namespace Orcses\PhpLib\Database\Schema\Columns;


use Orcses\PhpLib\Utility\Str;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;


abstract class Column
{
  const NAME        = 'name';
  const TYPE        = 'type';
  const LENGTH      = 'length';
  const NULL        = 'null';
  const DEFAULT     = 'default';
  const HAS_DEFAULT = 'has_default';
  const COMMENT     = 'comment';
  const EXPRESSION  = 'expression';
  const AFTER       = 'after';

  protected $name;

  protected $type;

  protected $length;

  protected $null = false;

  protected $default, $has_default;

  protected $comment;

  protected $expression;

  protected $after;

  protected $properties = [];

  protected $props = [
    self::LENGTH, self::NULL, self::DEFAULT, self::HAS_DEFAULT,
    self::COMMENT, self::EXPRESSION, self::AFTER
  ];

  protected $default_props = [
    self::LENGTH, self::HAS_DEFAULT
  ];

  protected $created = false;

  // name
  // type (length) : int (%d) (un)signed, decimal (%d,%d), varchar (%d), text (), datetime (), timestamp ()
  // default :
  // null :
  // auto_increment : int
  // on_update :
  // indexes : primary, unique, spatial


  public final function __construct(string $name, ColumnType $type, array $properties = null)
  {
    $this->setName( $name );

    $this->setType( $type );

    $this->setInitialProperties( $properties ?: [] );

    $this->onCreate();

    $this->created = true;
  }


  /**
   * Performs additional initialization tasks in the sub_class
   */
  abstract protected function onCreate();


  /**
   * Gets all the properties of the sub_class
   */
  protected function getProps()
  {
    return [];
  }


  protected function isCreated(): bool
  {
    return $this->created;
  }


  protected function setInitialProperties(array $properties)
  {
    $this->props = array_unique(
      array_merge( $this->props, $this->getProps() )
    );

    $this->default_props = array_unique(
      array_merge( $this->default_props, array_keys($properties) )
    );
    pr(['usr' => __FUNCTION__, 'default_props' => $this->default_props]);

    foreach ($this->default_props as $prop) {

      $setter = 'set' . Str::titleCase( $prop );

      $value = $properties[ $prop ] ?? null;

      $value
        ? call_user_func( [$this, $setter], $value )
        : call_user_func( [$this, $setter] );
    }
  }


  public function getProperties()
  {
    if( ! $this->properties){

      foreach ($this->props as $prop) {

        $getter = 'get' . Str::titleCase( $prop );

        $this->properties[ $prop ] = call_user_func([$this, $getter]);
      }
    }

    return $this->properties;
  }


  public function setName(string $name)
  {
    if('' === ($name = trim($name))){
      throw new InvalidArgumentException("Invalid column name supplied");
    }

    $this->name = $name;

    return $this;
  }


  public function getName()
  {
    return $this->name;
  }


  public function setType(ColumnType $type)
  {
    $this->type = $type;

    $this->setLength();

    return $this;
  }


  /** @return string */
  public function getTypeName()
  {
    return $this->getType()->getName();
  }


  /** @return ColumnType */
  public function getType()
  {
    return $this->type;
  }


  public function setLength(int $length = 0)
  {
    $column_type = $this->getType();

    if(0 === func_num_args()){
      $length = $column_type->getDefaultLength();
    }
    /*elseif( ! $column_type->validateLength( $length )){
      // ToDo: leave these checks for the underlying Database, then, throw any exceptions from there
      throw new InvalidArgumentException(
        "Invalid length '{$length}' for column type '{$column_type->getName()}'"
        . ", suggests '{$column_type->getDefaultLength()}'"
      );
    }*/

    $this->length = $length;

    return $this;
  }


  public function getLength()
  {
    return $this->length;
  }


  /**
   * @param bool $flag
   * @return static
   */
  // When null !== true (i.e NOT NULL), set default = '' (removes DEFAULT the clause)
  public function setNull(bool $flag = true)
  {
    pr(['usr' => __FUNCTION__, '$flag' => $flag, 'func_get_args' => func_get_args()]);

    $this->null = $flag;

    if($this->null !== true && is_null($this->getDefault())){
      $this->setHasDefault(false);
    }

    return $this;
  }


  public function getNull()
  {
    return $this->null;
  }


  public function setHasDefault(bool $flag = false)
  {
    $this->has_default = $flag;
  }


  public function getHasDefault()
  {
    return $this->has_default;
  }


  // When default === '' (empty string), remove the DEFAULT clause
  public function setDefault(string $default = null)
  {
    $matched_default = $this->getType()->matchValue( $default );

    if(false === $matched_default){
      pr(['usr' => __FUNCTION__, '$default' => $default, '$matched_default' => false, '$name' => $this->getName()]);
      // ToDo: throw Exception
      throw new InvalidArgumentException(
        "Column type '{$this->getTypeName()}' cannot have default value '{$default}'"
      );
    }

    $this->setHasDefault(true);

    $this->default = $matched_default;

    if(is_null($this->default)){
      $this->setNull(true);
    }

    return $this;
  }


  public function getDefault()
  {
    return $this->default;
  }


  public function setComment(string $comment)
  {
    $this->comment = $comment;

    return $this;
  }


  public function getComment()
  {
    return $this->comment;
  }


  public function setExpression(string $expression)
  {
    $this->expression = $expression;
  }


  public function getExpression()
  {
    return $this->expression;
  }


  public function setAfter(string $column)
  {
    $this->after = $column;

    return $this;
  }


  public function getAfter()
  {
    return $this->after;
  }


}

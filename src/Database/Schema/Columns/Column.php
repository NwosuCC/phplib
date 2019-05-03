<?php

namespace Orcses\PhpLib\Database\Schema\Columns;


use Orcses\PhpLib\Utility\Str;


abstract class Column
{
  const NAME       = 'name';
  const TYPE       = 'type';
  const LENGTH     = 'length';
  const NULL       = 'null';
  const DEFAULT    = 'default';
  const NO_DEFAULT = 'no_default';
  const PRIMARY    = 'primary';
  const COMMENT    = 'comment';
  const EXPRESSION = 'expression';
  const AFTER      = 'after';

  protected $name;

  protected $type;

  protected $length;

  protected $null = false;

  protected $default, $no_default;

  protected $primary;

  protected $comment;

  protected $expression;

  protected $after;

  protected $properties = [];

  protected $props = [
    self::LENGTH, self::NULL, self::DEFAULT, self::NO_DEFAULT,
    self::PRIMARY, self::COMMENT, self::EXPRESSION, self::AFTER
  ];

  protected $default_props = [
    self::LENGTH, self::NO_DEFAULT
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


  /** @return ColumnType */
  public function getType()
  {
    return $this->type;
  }


  public function setLength(int $length = 0)
  {
    $this->length = $this->getType()->syncLength( $length );

    return $this;
  }


  public function getLength()
  {
    return $this->length;
  }


  // When null !== true (i.e NOT NULL), set default = '' (removes DEFAULT the clause)
  public function setNull(bool $flag = true)
  {
    pr(['usr' => __FUNCTION__, '$flag' => $flag, 'func_get_args' => func_get_args()]);

    $this->null = $flag;

    if($this->null !== true && is_null($this->getDefault())){
      $this->setNoDefault(true);
    }

    return $this;
  }


  public function getNull()
  {
    return $this->null;
  }


  protected function setNoDefault(bool $flag = true)
  {
    $this->no_default = $flag;
  }


  public function getNoDefault()
  {
    return $this->no_default;
  }


  // When default === '' (empty string), remove the DEFAULT clause
  public function setDefault(string $default = null)
  {
    $this->setNoDefault(false);

    $this->default = $default;

    if(is_null($this->default)){
      $this->setNull(true);
    }

    return $this;
  }


  public function getDefault()
  {
    return $this->default;
  }


  public function setPrimary()
  {
    $this->primary = true;

    return $this;
  }


  public function getPrimary()
  {
    return $this->primary;
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

<?php

namespace Orcses\PhpLib\Database\Schema\Columns;


use Orcses\PhpLib\Utility\Str;
use Orcses\PhpLib\Exceptions\Database\Schema\InvalidColumnPropertyException;


abstract class Column
{
  const NAME       = 'name';
  const TYPE       = 'type';
  const LENGTH     = 'length';
  const NULL       = 'null';
  const DEFAULT    = 'default';
  const PRIMARY    = 'primary';
  const COMMENT    = 'comment';
  const EXPRESSION = 'expression';
  const AFTER      = 'after';

  protected $name;

  protected $type;

  protected $length;

  protected $null;

  protected $default;

  protected $primary;

  protected $comment;

  protected $expression;

  protected $after;

  protected $properties;

  protected $props = [
    self::LENGTH, self::NULL, self::DEFAULT, self::PRIMARY, self::COMMENT, self::EXPRESSION, self::AFTER
  ];

  // name
  // type (length) : int (%d) (un)signed, decimal (%d,%d), varchar (%d), text (), datetime (), timestamp ()
  // default :
  // null :
  // auto_increment : int
  // on_update :
  // indexes : primary, unique, spatial


  public function __construct(string $name, ColumnType $type, array $properties = null)
  {
    $this->setName( $name );

    $this->setType( $type );

    $this->properties = $properties ?: [];

    $this->setProperties();
  }


  // Get the specific props of this sub_class
  abstract protected function getProps();


  protected function setProperties()
  {
    $this->props = array_unique( array_merge( $this->props, $this->getProps() ) );
    pr(['usr' => __FUNCTION__, '$this->props' => $this->props, '$this->properties' => $this->properties]);

    foreach ($this->properties as $property => $value) {

      if( ! in_array($property, $this->props)){

        throw new InvalidColumnPropertyException( $property );
      }

      $method_name = 'set' . Str::titleCase( $property );

      call_user_func( [$this, $method_name], $value );
    }

    return $this;
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

    return $this;
  }


  public function getType()
  {
    return $this->type;
  }


  public function setLength(int $length)
  {
    $this->length = $length;

    return $this;
  }


  public function getLength()
  {
    return $this->length;
  }


  public function setNull(bool $flag = true)
  {
    $this->null = $flag;

    return $this;
  }


  public function getNull()
  {
    return $this->null;
  }


  public function setDefault(string $default)
  {
    $this->default = $default;

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


  public function setComment($comment)
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


  public function setAfter(Column $after)
  {
    $this->after = $after;

    return $this;
  }


  public function getAfter()
  {
    return $this->after;
  }


  protected function getDefaultLengthForType()
  {
    $type = strtoupper( $this->getType() );

    // ToDo: resolve this
    return ;
  }


}

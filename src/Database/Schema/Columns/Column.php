<?php

namespace Orcses\PhpLib\Database\Schema\Columns;


use Orcses\PhpLib\Exceptions\Database\Schema\InvalidColumnPropertyException;
use Orcses\PhpLib\Utility\Str;

abstract class Column
{
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

  protected $props = [];

  // name
  // type (length) : int (%d) (un)signed, decimal (%d,%d), varchar (%d), text (), datetime (), timestamp ()
  // default :
  // null :
  // auto_increment : int
  // on_update :
  // indexes : primary, unique, spatial


  public function __construct(string $name, string $type, array $properties = null)
  {
    $this->setName( $name );

    $this->setType( $type );

    $this->properties = $properties ?: [];

    $this->setProperties();
  }


  protected final function getProps()
  {
    return [
      'length', 'null', 'default', 'primary', 'comment', 'expression', 'after'
    ];
  }


  protected function setProperties()
  {
    $this->props = array_unique( $this->props + $this->getProps() );

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


  public function setType(string $type)
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
    // If this column is REAL NUMBER, set the Precision and Scale

    $this->length = $length;

    return $this;
  }


  public function getLength()
  {
    return $this->length;
  }


  public function setNull(bool $flag)
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


}
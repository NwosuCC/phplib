<?php

namespace Orcses\PhpLib\Database\Schema;


class Table
{
  protected $name;

  protected $exists;

  protected $collation = 'utf8mb4_unicode_ci';  // put in config


  public function __construct(string $name)
  {
    $this->name = $name;
  }


  public function getName()
  {
    return $this->name;
  }


  public function setExists(bool $flag)
  {
    $this->exists = $flag;

    return $this;
  }


  public function getExists()
  {
    return $this->exists;
  }


  /*protected function addAttributes(array $attributes)
  {
    foreach($attributes as $name => $value){

      if($name === 'collation' && $value){
        // Over-write default
        $this->collation = $value;
      }

    }

  }*/


}


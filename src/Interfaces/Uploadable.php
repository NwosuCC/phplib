<?php

namespace Orcses\PhpLib\Interfaces;


interface Uploadable
{

  public function getSize();


  public function storeAs(string $name, string $disk, array $permissions = null);


  public function store(string $disk, array $permissions = null);


  public function storageName();


  public function disk();


  public function getConfig(string $key = null);


  public function size();


  public function name();


  public function tmpName();


  public function extension();


  public function category();


  public function fullMimeType();


}
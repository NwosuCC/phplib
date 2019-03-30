<?php

namespace Orcses\PhpLib\Models;

use Orcses\PhpLib\Models\Model;


class Cache extends Model
{
  protected $table = 'cache';

  protected $fillable = ['key', 'value', 'expiration'];


}
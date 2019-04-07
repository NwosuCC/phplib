<?php

namespace Orcses\PhpLib\Access;


use Orcses\PhpLib\Models\Model;
use Orcses\PhpLib\Traits\Auth\HasApiToken;
use Orcses\PhpLib\Traits\Auth\AuthenticatesUser;
use Orcses\PhpLib\Interfaces\Auth\Authenticatable;


class User extends Model implements Authenticatable
{
  use AuthenticatesUser, HasApiToken;


}


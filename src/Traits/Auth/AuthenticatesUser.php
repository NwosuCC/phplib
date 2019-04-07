<?php

namespace Orcses\PhpLib\Traits\Auth;


trait AuthenticatesUser
{

  public function retrieveByCredentials(array $vars)
  {
    if( ! $user = $vars['email'] ?? $vars['username'] ?? null){
      return null;
    }

    $password = $vars['password'];

    $where = [
      'user|a' => [
        "email" => $user,
        'un|o'=> [
          "username" => $user,
        ],
      ]
    ];

    return [$where, $password ?? null];
  }


  public function validateSession()
  {

  }


}
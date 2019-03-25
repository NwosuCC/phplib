<?php

namespace Orcses\PhpLib\Access;


use Net\Models\User;
use Orcses\PhpLib\DatabaseCache;


class Token
{
  public function __construct()
  {
  }


  public function grantFreshToken(User $user)
  {
    $user_info = [];

    foreach($user->tokenFields() as $field){
      $user_info[] = $user->getAttribute($field);
    };

    list($key, $token, $expiry) = JWToken::getToken( implode('.', $user_info));

    if($saved = DatabaseCache::store($key, $token, $expiry)){
      return $token;
    }

    return null;
  }


  public function verifyToken($token)
  {
    if($verified = JWToken::verifyToken($token)) {

      [$key, $token, $expiry, $user_info] = $verified;

      if($isValidToken = DatabaseCache::fetch($key)){
        return [
          'token' => $token, 'user' => $user_info, 'expiry' => $expiry
        ];
      }
    }

    return Auth::logout(3) ?? null;
  }



}
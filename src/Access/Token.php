<?php

namespace Orcses\PhpLib\Access;


use Orcses\PhpLib\Cache\DatabaseCache;


class Token
{
  protected static $error;

  /**
   * Retrieves a new token
   * @param array  $user_info The user info to embed in the token
   * @return string|null
   */
  public static function generate(array $user_info = [])
  {
    list($key, $token, $expiry) = JWToken::getToken( implode('.', $user_info));

    if($saved = DatabaseCache::store($key, $token, $expiry)){
      return $token;
    }

    return null;
  }


  /**
   * Retrieves a new token
   * @param string  $token
   * @return array
   */
  public static function verify(string $token)
  {
    if($verified = JWToken::verifyToken($token)) {

      [$key, $token, $expiry, $id] = $verified;

      if($isValidToken = DatabaseCache::get($key)){

        return compact('token', 'id', 'expiry');
      }
    }

    static::$error = JWToken::error();

    return [];
  }


  public static function error()
  {
    return static::$error ?: null;
  }



}
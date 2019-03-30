<?php

namespace Orcses\PhpLib\Access;


use Orcses\PhpLib\Cache\DatabaseCache;
use Orcses\PhpLib\Request;


class Token
{
  public function __construct()
  {
  }


  /**
   * Retrieves a new token
   * @param array  $user_info The user info to embed in the token
   * @return string|null
   */
  public static function get(array $user_info = [])
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
  public static function verifyToken(string $token)
  {
    if($verified = JWToken::verifyToken($token)) {

      [$key, $token, $expiry, $user_info] = $verified;

      if($isValidToken = DatabaseCache::get($key)){
        return [
          'token' => $token, 'user' => $user_info, 'expiry' => $expiry
        ];
      }
    }

    return [];
  }



}
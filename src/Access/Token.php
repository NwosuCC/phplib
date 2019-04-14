<?php

namespace Orcses\PhpLib\Access;


class Token
{
  protected static $error;

  /**
   * Retrieves a new token
   * @param array  $user_info   The user data to embed in the token
   * @param bool   $only_value  If true, returns only the token value, else, the entire array
   * @return mixed
   */
  public static function generate(array $user_info = [], bool $only_value = true)
  {
    if($token = JWToken::getToken( implode('.', $user_info))){

      return $only_value ? $token['value'] : $token;
    }

    return null;
  }


  /**
   * Retrieves a new token
   * @param string  $token
   * @param bool    $only_data  If true, returns only the user data, else, the entire array
   * @return mixed
   */
  public static function verify(string $token, bool $only_data = true)
  {
    if($verified = JWToken::verifyToken($token)) {

        return $only_data ? explode('.', $verified['data']) : $verified;
    }

    static::$error = JWToken::error();

    return [];
  }


  public static function error()
  {
    return static::$error ?: null;
  }



}
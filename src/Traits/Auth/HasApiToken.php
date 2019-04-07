<?php

namespace Orcses\PhpLib\Traits\Auth;


use Orcses\PhpLib\Access\JWToken;
use Orcses\PhpLib\Cache\DatabaseCache;


trait HasApiToken
{

  protected static $error;


  public function retrieveByToken(string $token)
  {
    pr(['usr' => __FUNCTION__, '$token' => $token]);

    if( ! $id = $this->verify($token)) {
      return null;
    }

    return [$this->getKeyName() => $id];
  }


  /**
   * Retrieves a new token
   * @param string $id The user id to embed in the token
   * @return string|null
   */
  public function generate(string $id)
  {
    $token = JWToken::getToken( $id );
    pr(['usr' => __FUNCTION__, '$token key' => $token['key']]);

    if($saved = DatabaseCache::store($token['key'], $token['value'], $token['expiry'])){
      return $token['value'];
    }

    return null;
  }


  /**
   * Verifies the supplied token
   * @param string  $token
   * @return string|null
   */
  public function verify(string $token)
  {
    if($verified = JWToken::verifyToken($token)) {

      if($is_valid_token = DatabaseCache::get( $verified['key'] )){

        return $verified['data'];
      }
    }

    static::$error = JWToken::error();

    return null;
  }


  /**
   * Expires the auth user's token (logout)
   * @return bool
   */
  public function expireToken()
  {
    if( ! $user = auth()->user() or ! $verified = JWToken::verifyToken($user->token)) {
      return true;
    }

    return DatabaseCache::expire($verified['key']);
  }


  public function tokenError()
  {
    return static::$error ?: null;
  }


}
<?php

namespace Orcses\PhpLib\Traits\Auth;


use Orcses\PhpLib\Access\Token;
use Orcses\PhpLib\Cache\DatabaseCache;


trait HasApiToken
{

  protected static $error;


  public function retrieveByToken(string $token)
  {
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
    $token = Token::generate( [$id], false );

    if($value = $token['value']){

      if($saved = DatabaseCache::store($token['key'], $value, $token['expiry'])){
        return $token['value'];
      }
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
    if($verified = Token::verify($token, false)) {

      if($is_valid_token = DatabaseCache::get( $verified['key'] )){

        return $verified['data'];
      }
    }

    static::$error = Token::error();

    return null;
  }


  /**
   * Expires the auth user's token (logout)
   * @return bool
   */
  public function expireToken()
  {
    if( ! $user = auth()->user() or ! $verified = Token::verify($user->token)) {
      return true;
    }

    return DatabaseCache::expire($verified['key']);
  }


  public function tokenError()
  {
    $error = static::$error;

    return static::$error = null ?: $error;
  }


}
<?php

namespace Orcses\PhpLib\Access;

use Exception;
use Firebase\JWT\JWT;
use Orcses\PhpLib\Utility\Arr;


class JWToken
{
  protected static $algorithm = ['HS256'];


  protected static function key()
  {
    // ToDo: Refactor, import $app, throw Exception if not exists
    return env('APP_KEY');
  }


  protected static function keyId($payload)
  {
    [$iat, $iss, $exp] = Arr::pickOnly($payload, ['iat', 'iss', 'exp'],false);

    $user_info = $payload['inf']['user'];

    $public_key = substr( sha1($iat . $iss . $user_info), 9);

    $private_key = md5($public_key . $exp . '%bd# Ax9(^@');

    return [$private_key, $public_key, $exp];
  }


  public static function getToken(string $user_info = null)
  {
    $key = static::key();

    // ToDo: Refactor, import $app, throw Exception if not exists
    $payload = [
      "iss" => env('APP_URL'),
      "aud" => env('APP_URL'),
      "iat" => $now = time(),
      "nbf" => $now - 10,
      "exp" => $now + (60 * 60 * 24 * 1),  // 1 day
      "inf" => [
        'user' => $user_info
      ]
    ];

    list($private_key_id, $public_key_id, $expiry) = static::keyId($payload);

    $algorithm = static::$algorithm[0];

    /**
     * IMPORTANT:
     * You must specify supported algorithms for your application. See
     * https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40
     * for a list of spec-compliant algorithms.
     */
    $token = JWT::encode($payload, $key, $algorithm, $public_key_id);

    return [$private_key_id, $token, $expiry];
  }


  public static function verifyToken(string $token)
  {
    if(empty($token)){
      return null;
    }

    /**
     * You can add a leeway to access for when there is a clock skew times between
     * the signing and verifying servers. It is recommended that this leeway should
     * not be bigger than a few minutes.
     *
     * Source: http://self-issued.info/docs/draft-ietf-oauth-json-web-token.html#nbfDef
     */
    JWT::$leeway = 60; // $leeway in seconds

    $key = static::key();

    try {
      $decoded = JWT::decode($token, $key, static::$algorithm);
    }
    catch (Exception $e) {
      return false;
    }

    $decoded_array = (array) $decoded;
    $decoded_array['inf'] = (array) $decoded_array['inf'];

    $user_info = $decoded_array['inf']['user'];

    list($private_key_id, $public_key_id, $expiry) = static::keyId($decoded_array);

    return $public_key_id ? [$private_key_id, $token, $expiry, $user_info] : null;
  }

}
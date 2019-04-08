<?php

namespace Orcses\PhpLib\Access;


use Orcses\PhpLib\Request;
use Orcses\PhpLib\Utility\Dates;
use Orcses\PhpLib\Routing\Router;
use Orcses\PhpLib\Interfaces\Auth\Authenticatable;


final class Auth
{
  protected static $throttle_delay = 15;

  protected static $auth;

  /** @var \Orcses\PhpLib\Models\Model $user */
  protected $user, $bound = false;

  protected $id;


  public function __construct(Authenticatable $user)
  {
    if( ! static::$auth){
      $this->user = $user;

      static::$auth = $this;
    }
  }


  /**
   * @param array $replaces
   * @return array
   */
  public static function success($replaces = [])
  {
    $info = ['user' => self::user()->toArray()];

    return success( report()::ACCESS, $info, $replaces);
  }


  public static function error($code, $replaces = [])
  {
    return error( report()::ACCESS, $code, $replaces );
  }


  /** @return $this */
  public static final function auth()
  {
    return static::$auth;
  }

  public static function user()
  {
    return static::auth()->user;
  }


  public static function id()
  {
    return static::auth()->id;
  }


  public static function check()
  {
    return !! static::auth()->user();
  }


  protected static function set(string $key, $value)
  {
    if(self::check()){
      self::auth()->user->imposeAttribute($key, $value);
    }
  }


  protected static function throttleDelay()
  {
    return static::$throttle_delay;
  }


  public static function reset()
  {
    static::$auth->user = static::$auth->id = null;

    return false;
  }


  /**
   * Called, for example, from LoginController::class
   * @param array $vars
   * @return bool
   */
  public static function attempt(array $vars)
  {
    Router::setLoginRouteName( Request::currentRouteParams() );

    $auth = static::auth();

    [$where, $password] = $auth->user->retrieveByCredentials($vars);

    $auth->retrieveUser($where, $password);

    if( self::user()){

      self::updateStats();

      // If User class uses trait 'HasApiToken'
      if(method_exists($auth->user, 'generate')){

        if( ! $token = call_user_func([$auth->user, 'generate'], self::id())){
          return static::reset();
        }

        self::set('token', $token);
      }

    }
    else{
//      $error = Auth::error(self::INVALID_CREDENTIALS);
      $error = [];

      // [Read from 'Settings'] Prevent rapid multiple Login attempts
      Request::throttle( Router::getLoginRouteName(), $error);
    }

    return !empty(self::user());
  }


  /**
   * Called, for example, from \Middleware\Auth\Api::class
   * @param string $token
   * @return bool
   */
  public static function verify(string $token)
  {
    $auth = static::auth();

    $where = $auth->user->retrieveByToken($token);

    $auth->retrieveUser($where);

    return !empty(self::user());
  }


  protected function retrieveUser(array $where = null, string $password = null)
  {
    if( ! $where || ! $user = $this->user->where($where)->first()){
      return false;
    }

    if( ! is_null($password)){
      $hashed_password = $user->getAttribute('password');

      if( ! password_verify($password, $hashed_password)){
        return false;
      }
    }

    return $this->setAuthUser($user);
  }


  protected function setAuthUser(Authenticatable $user)
  {
    $this->user = $user;

    $this->id = $this->user->getKey();

    $this->user->removeAttribute('password');

    // Set authenticated user one time
    static::$auth = $this;

    return true;
  }


  public static function logout(bool $restrain = false)
  {
    self::updateStats( $restrain );

    // unset session

    // If User class uses trait 'HasApiToken', expire token
    if(method_exists(static::class, 'expireToken')){

      call_user_func([static::class, 'expireToken']);
    }
  }


  /**
   * @param bool $restrain If true, sets duration (minutes) before a new login attempt is accepted from this User
   * @return bool
   */
  protected static function updateStats(bool $restrain = false)
  {
    if( ! self::id()) {
      return false;
    }

    $update_values = [
      'last_login' => Dates::now(),
      'delay_login' => $restrain ? intval( static::$throttle_delay ) : 0
    ];

    return self::user()->update($update_values);
  }


}
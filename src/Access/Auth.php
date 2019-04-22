<?php

namespace Orcses\PhpLib\Access;


use Carbon\Carbon;
use Orcses\PhpLib\Request;
use Orcses\PhpLib\Routing\Router;
use Orcses\PhpLib\Interfaces\Auth\Authenticatable;


final class Auth
{
  const INV_CREDENTIALS = '01';

  const NOT_AUTHORIZED = '02';

  const ERROR_DEFAULT = '03';

  const THROTTLE_DELAY = '04';

  const INVALID_TOKEN = '05';


  protected static $replaces = [
    self::INVALID_TOKEN
  ];


  protected static $throttle_delay = 15;

  protected static $auth;

  /**
   * @var \Orcses\PhpLib\Access\User | \Net\Models\User $user
   *
   * The injected Authenticatable or auth-bound User
   */
  protected $user;

  protected $id;


  public function __construct(Authenticatable $user)
  {
    if( ! static::$auth){
      $this->user = $user;

      static::$auth = $this;
    }
  }


  /**
   * @param array  $replaces
   * @param string $index
   * @return array
   */
  public static function success($replaces = [], $index = '01')
  {
    $info = ['user' => self::user()->toArray()];

    return success( report()::ACCESS, $index, $replaces, $info );
  }


  public static function error($code, $replaces = [])
  {
    if(in_array($code, self::$replaces) && $more_info = self::moreInfo($code)){

      if( ! array_key_exists($more_info[0], $replaces)){

        $replaces[ $more_info[0] ] = $more_info[1] ?: '';
      }
    }

    return error( report()::ACCESS, $code, $replaces );
  }


  /**
   * @param  $code
   * @return array
   */
  protected static function moreInfo($code)
  {
    $info = [
      '05' => [
        'token_error', ($error = self::getTokenError()) ? $error . '. Please, log in to continue' : ''
      ]
    ];

    return $info[ $code ] ?? [];
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
    Router::setLoginRouteName();

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

    return ! empty(self::id());
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

    return ! empty(self::id());
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


  public static function getTokenError()
  {
    // If User class uses trait 'HasApiToken'
    return method_exists(self::auth()->user, 'tokenError')
      ? call_user_func([self::auth()->user, 'tokenError'])
      : null;
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

    self::user()->{'last_login'} = Carbon::now();

    self::user()->{'delay_login'} = $restrain ? intval( static::$throttle_delay ) : 0;

    return self::user()->save();
}


}
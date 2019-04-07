<?php

namespace Orcses\PhpLib\Access;


use Orcses\PhpLib\Request;
use Orcses\PhpLib\Response;
use Orcses\PhpLib\Models\Model;
use Orcses\PhpLib\Utility\Dates;
use Orcses\PhpLib\Routing\Router;
use Orcses\PhpLib\Interfaces\Modelable;


final class Auth_old implements Modelable
{
  protected $table = 'users';

  const INVALID_CREDENTIALS = 1;
  const NOT_AUTHORIZED = 2;
  const PLEASE_CONTINUE = 3;
  const THROTTLE_DELAY = 6;
  const INVALID_TOKEN = 7;

  protected static $throttle_delay = 15;

  protected static $auth, $token_fields = [];

  /** @var \Orcses\Phplib\Models\PseudoModel $model */
  protected $model;

  /** @var \Orcses\PhpLib\Models\Model $user */
  protected $user, $bound = false;

  protected $id;


  protected function __construct()
  {
    $this->model = Model::pseudo($this);

    static::$auth = $this;
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


  public function getTable()
  {
    return $this->table;
  }


  /**
   * @param  \Orcses\PhpLib\Models\Model $user
   */
  public function bind(Model $user)
  {
    if( ! $this->bound){
      $this->model = $user->refresh();

      $this->authenticate(['id' => self::auth()->id()]);

      $this->bound = true;
    }
  }


  /** @return $this */
  public static final function auth()
  {
    if( ! static::$auth){
      new static();
    }

    return static::$auth;
  }


  public static function check(bool $log_out = false)
  {
    if( ! ($auth = static::auth()->user) && $log_out){

      Request::abort( Auth::error(Auth::PLEASE_CONTINUE), true);
    }

    return !! $auth;
  }


  public static function user()
  {
    return static::auth()->user;
  }


  public static function id()
  {
    return static::auth()->id;
  }


  protected static function set(string $key, $value)
  {
    if(self::id()){
      self::auth()->user->imposeAttribute($key, $value);
    }
  }


  public static function setTokenFields(array $token_fields)
  {
    self::$token_fields = $token_fields;
  }


  public static function throttleDelay()
  {
    return static::$throttle_delay;
  }


  public static function attempt($vars)
  {
    Router::setLoginRouteName( Request::currentRouteParams() );

    static::auth()->authenticate($vars);

    if( self::user()){
      self::updateStats();

      if( ! $token = Token::generate( static::getUserInfo() )) {
        // User token not set. Abort and try again
        Request::abort( Auth::error(Auth::PLEASE_CONTINUE), true);
      }

      self::set('token', $token);
    }
    else{
      $error = Auth::error(self::INVALID_CREDENTIALS);

      // Prevent rapid multiple Login attempts
      Request::throttle( Router::getLoginRouteName(), $error);
    }

    return !empty(self::user());
  }


  public static function verify(string $token)
  {
    if($verified = Token::verify($token)) {

      $id = array_shift( $verified['user_info'] );

      static::auth()->authenticate(['id' => $id]);

      if(self::user()){ return; }
    }

    $replaces = ['token_error' => Token::error()];

    self::logout(Auth::INVALID_TOKEN, $replaces);
  }


  protected function authenticate($vars = [])
  {
    $where_active = [
      'status' => ['BETWEEN', 1, 2],
      'delay_login|b' => 'delay_login <= floor((unix_timestamp() - unix_timestamp(last_login)) / 60)'
    ];

    if($user = $vars['email'] ?? $vars['username'] ?? null){
      // Login:
      $password = $vars['password'];

      $where = [
        'user|a' => [
          "email" => $user,
          'un|o'=> [
            "username" => $user,
          ],
        ]
      ];
    }
    elseif($id = $vars['id'] ?? null){
      // Token:
      $where = [$this->model->getKeyName() => $id];
    }

    if( ! empty($where)){
      $where = array_merge($where, $where_active);

      if( ! $this->retrieveUser($where, $password ?? null)){
        static::$auth->user = static::$auth->id = null;

        return self::check(true);
      }
    }

    return false;
  }


  protected function retrieveUser(array $where, string $password = null)
  {
    if( ! $result = $this->model->where($where)->first()){
      return false;
    }

    if( ! is_null($password)){
      $hashed_password = $result->getAttribute('password');

      if( ! password_verify($password, $hashed_password)){
        return false;
      }
    }

    return $this->setAuthUser($result);
  }


  protected function setAuthUser(Model $result)
  {
    $this->user = $result;
    $this->id = $this->user->getKey();

    // Set authenticated user one time
    static::$auth = $this;

    static::auth()->user->removeAttribute('password');

    return true;
  }


  protected static function getUserInfo()
  {
    $user_info = [];

    foreach(self::$token_fields as $field) {
      $user_info[] = self::user()->getAttribute( $field );
    }

    if( ! $user_info){
      $user_info = [
        self::user()->{'email'} ?? self::user()->{'username'} ?? ''
      ];
    }

    // Add 'id' to be used internally for token verification
    array_unshift( $user_info, self::id() );

    return $user_info;
  }


  public static function logout($code, $replaces = []){
    self::updateStats( (int) $code === Auth::THROTTLE_DELAY );

    Response::get( Auth::error($code, $replaces) )->send();
  }


  // ToDo: refactor to make generic, else, move to outer App level
  // If $restrain is true, sets duration (minutes) before a new login attempt is allowed from this User
  protected static function updateStats($restrain = false)
  {
    if( ! self::id()) {
      return false;
    }

    $update_values = [
      'last_login' => Dates::now(),
      'timeout' => $restrain ? intval( static::$throttle_delay ) : 0
    ];

    return self::user()->update($update_values);
  }


}
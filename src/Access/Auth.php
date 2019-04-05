<?php

namespace Orcses\PhpLib\Access;


use Orcses\PhpLib\Request;
use Orcses\PhpLib\Response;
use Orcses\PhpLib\Models\Model;
use Orcses\PhpLib\Utility\Dates;
use Orcses\PhpLib\Routing\Router;
use Orcses\PhpLib\Interfaces\Modelable;


final class Auth implements Modelable
{
  protected $table = 'users';

  const REPORT_KEY = 'access';

  const INVALID_CREDENTIALS = 1;
  const NOT_AUTHORIZED = 2;
  const PLEASE_CONTINUE = 3;
  const THROTTLE_DELAY = 6;

  protected static $throttle_delay = 15;

  protected static $auth, $token_fields = [];

  /** @var \Orcses\Phplib\Models\PseudoModel $model */
  protected $model;

  /** @var \Orcses\PhpLib\Models\Model $user */
  protected $user;

  protected $id;


  protected function __construct()
  {
    $this->model = Model::pseudo($this);

    static::$auth = $this;
  }


  /**
   * @return array
   */
  public static function success()
  {
    $replaces = [
      'name' => self::user()->name
    ];

    $info = ['user' => self::user()->toArray()];

    return [self::REPORT_KEY, [0, $replaces], $info];
  }


  public static function error($code)
  {
    return [self::REPORT_KEY, $code];
  }


  public function getTable()
  {
    return $this->table;
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
    if( !($auth = static::auth()->user) && $log_out){

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

    self::logout(Auth::PLEASE_CONTINUE);
  }


  protected function authenticate($vars = [])
  {
    $where_active = [
      'status' => ['BETWEEN', 1, 2],
      'timeout|b' => 'timeout <= floor((unix_timestamp() - unix_timestamp(last_login)) / 60)'
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
    pr(['usr' => __FUNCTION__, '$where' => $where]);

    if( ! empty($where)){
      $where = array_merge($where, $where_active);

      $this->setAuthUser($where, $password ?? null);
    }
  }


  protected function setAuthUser(array $where, string $password = null)
  {
    if($result = $this->model->where($where)->first()){

      if($password){
        $hashed_password = $result->getAttribute('password');

        if( ! password_verify($password, $hashed_password)){
          return;
        }
      }

      $this->user = $result;
      $this->id = $this->user->getKey();

      // Set authenticated user one time
      static::$auth = $this;

      static::auth()->user->removeAttribute('password');
    }
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


  public static function logout($code){
    self::updateStats( (int) $code === Auth::THROTTLE_DELAY );

    Response::get( Auth::error($code) )->send();
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
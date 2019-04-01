<?php

namespace Orcses\PhpLib\Access;


use Orcses\PhpLib\Request;
use Orcses\PhpLib\Response;
use Orcses\PhpLib\Utility\Str;
use Orcses\PhpLib\Models\Model;
use Orcses\PhpLib\Utility\Dates;
use Orcses\PhpLib\Routing\Router;
use Orcses\PhpLib\Interfaces\Modelable;


final class Auth implements Modelable
{
  protected $table = 'users';

  const REPORT_KEY = 'access';

  const INV_CREDENTIALS = 1;
  const NOT_AUTHORIZED = 2;
  const PLS_CONTINUE = 3;
  const THROTTLE_DELAY = 6;

  protected static $throttle_delay = 15;

  protected static $auth, $token_fields = [];

  /** @var \Orcses\Phplib\Models\PseudoModel $model */
  protected $model;

  /** @var \Orcses\PhpLib\Models\Model $user */
  protected $user;

  protected $id;


  protected function __construct(){
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
  private static final function auth(){
    if( ! static::$auth){
      new static();
    }

    return static::$auth;
  }


  public static function check(bool $log_out = false){
    if( !($auth = static::auth()->user) && $log_out){
      Request::abort( Auth::error(Auth::PLS_CONTINUE), true);
    }

    return !! $auth;
  }


  public static function user(){
    return static::auth()->user;
  }


  public static function id(){
    return static::auth()->id;
  }


  protected static function set(string $key, $value){
    if(self::id()){
      self::auth()->user->imposeAttribute($key, $value);
      pr(['auth user' => self::auth()->user]);
    }
  }


  public static function setTokenFields(array $token_fields){
    self::$token_fields = $token_fields;
  }


  public static function throttleDelay(){
    return static::$throttle_delay;
  }


  public static function attempt($vars){
    $vars['password'] = Str::hashedPassword($vars['password']);

    static::auth()->authenticate($vars);

    if( self::user()){
      self::updateStats();

      if( ! $user_info = self::$token_fields){
        $user_info = [
          self::id(), self::user()->{'email'} ?? ''
        ];
      }

      if( ! $token =  Token::get( $user_info )) {
        // JWToken not set, please try again
        Request::abort( Auth::error(Auth::PLS_CONTINUE), true);
      }

      self::set('token', $token);
    }
    else{
      // Invalid credentials
      $error = Auth::error(self::INV_CREDENTIALS);

      // Prevent rapid multiple Login attempts
      Request::throttle( Router::LOGIN, $error);
    }

    return !empty(self::user());
  }


  protected function authenticate($vars = []){
    if(self::user()) {
      return true;
    }

    // Login:
    $user = $vars['email'] ?? $vars['username'];
    $password = $vars['password'];

    $where = [
      [
        [
          "email" => $user,
        ],
        [
          "username" => $user,
        ],
      ],
      'password' => $password,
      'status' => ['BETWEEN', 1, 2],
//      'timeout|b' => 'timeout <= floor((unix_timestamp() - unix_timestamp(last_login)) / 60)'
    ];

    if($result = $this->model->where($where)->first()){
      pr(['authenticate $result' => $result]);
      $this->user = $result;
      $this->id = $this->user->getKey();

      // Set authenticated user once and for all
      static::$auth = $this;

      static::auth()->user->removeAttribute('password');
    }

    return (!empty(Auth::user()));
  }


  public static function logout($code){
    self::updateStats( (int) $code === Auth::THROTTLE_DELAY );

    Response::get( Auth::error($code) )->send();
  }


  // ToDo: refactor to make generic, else, move to outer App level
  // If $restrict is true, sets duration (minutes) before a new login attempt is allowed from this User
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
<?php

namespace Orcses\PhpLib\Access;


use Net\Models\User;
use Orcses\PhpLib\Response;
use Orcses\PhpLib\Result;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Utility\Str;

final class Auth
{
  protected const BAD_PASSWORD = 1, NOT_AUTHORIZED = 2, CONTINUE = 3;

  private static $auth;

  private static $timeout_minutes = 15;


  private function __construct(){
    if( ! Auth::user()){
      self::logout(Auth::CONTINUE);
    }
  }


  private static final function auth(){
    return self::$auth;
  }


  private static function _set(string $key, $value){
    if(self::id()){
      self::auth()->user->{$key} = $value;
    }
  }


  /** @return User */
  public static function user(){
    return static::auth()->user;
  }


  public static function id(){
    return static::auth()->id ?? null;
  }

  public static function timeout(){
    return static::$timeout_minutes;
  }


  public static function attempt($vars){
    $vars['password'] = Str::hashedPassword($vars['password']);

    $report_index = 'access';  $error_code = 1;

    static::authenticate($vars);

    if(Auth::user()){
      self::update_login_info(['timeout' => 0]);

      if( ! $this->cacheToken()) {
        // JWToken not set, please try again
        Auth::logout(3);
      }

      $replaces = [ 'name' => Auth::user()->name ];
      $error_code = ['00', $replaces];
    }
    else{
      // Prevent Login Throttle
      Request::throttle( App::get_op('user.login'));
    }

    $login_result = [$report_index, $error_code, Auth::user()];

    $this->send_result($login_result);
  }


  protected static function authenticate($type, $vars = []){
    if(Auth::user()) {
      return true;
    }

    // ID obtained from current request token
    $user_id = self::$request['id'];

    // If $type is NOT provided, Role must be obtained from current request token
    if( ! $type){
      $type = self::$request['role'];
    }

    if($agent_params = self::get_agent_params($type)){
      list($table, $column_id, $column_name) = $agent_params;

      if(!empty($user_id)){
        // User::get_user() :: $vars['agent'] - 'user_id' => For normal user op
        // User::get_user() :: $vars['agent'] - 'super_u' => For op on user's down-line
        if(!empty($vars['agent'])){
          $column_id =  $vars['agent'];
        }

        $where = [
          $column_id => $user_id, 'status' => ['BETWEEN', [1,2]]
        ];
      }
      else{
        // Login:
        $user = $vars['user'] ?? '';
        $password = $vars['password'] ?? '';

        $where = [
          "email||$column_name" => ['=', [$user, $user]],
          'password' => $password,
          'status' => ['BETWEEN', [1,2]],
          '_blank_|q|s' => ['timeout <= floor((unix_timestamp() - unix_timestamp(last_login)) / 60)', []]
        ];
      }

      if($result = Queries::select($table, '', $where)->first()){
        $result['src'] = STORAGE_URL;
//                $result['tpl'] = $this->get_tpl_params($type);

        if($type === Auth::UserRole){
          if($country = stristr($result['country'], 'other_')){
            $result['country'] = 'other';
            $result['other_country'] = str_replace('other_', '', $country);
          }
        }

        $result['role'] = $type;
        $result['id'] = $result[ $column_id ];

        $this->user = $result;
        $this->id = self::$request['id'] ?? $this->user['id'];
        $this->op = self::$request['op'] ?? Request::get_data()['op'];

        if(empty($vars['password'])){
          $todo = $this->get_user_todo();

          if($todo and !in_array($this->op, App::todo_ops())){
            $this->user['todo'] = $todo;
          }
        }

        $this->user = (object) $this->user;

        // Set authenticated user once and for all
        self::$auth = $this;
      }
    }

    return (!empty(Auth::user()));
  }


  public static function logout($code, $restrict = false){
    self::updateStats( $restrict );

    $result = Result::prepare(['access', $code]);

    Response::package( $result )->send();
  }


  // ToDo: refactor to make generic, else, move to outer App level
  // If $restrict is true, sets duration (minutes) before a new login attempt is allowed from this User
  protected static function updateStats($restrict = false)
  {
    if(Auth::id()){
      $update_values = [
        'last_login|q' => 'now()'
      ];

      if($restrict) {
        $update_values['timeout_minutes'] = intval( static::$timeout_minutes );
      }

      Auth::user()->update($update_values);
    }
  }



}
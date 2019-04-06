<?php

namespace Orcses\PhpLib\Models;

use Orcses\PhpLib\Access\Auth;
use Orcses\PhpLib\Queries;
use Orcses\PhpLib\Utility\Arr;


class User extends Model
{
  protected $table = 'users';

  protected $fillable = [
    'first_name', 'last_name', 'middle_name', 'username',
    'email', 'password', 'phone', 'agree', 'user_id',
    'sex', 'center', 'timeout', 'last_login'
  ];

  protected $guarded = [
    'e_pin', 'password', 'email', 'sn'
  ];

  protected $hidden = [
    'e_pin', 'password', 'agree', 'captcha'
  ];

  protected $appends = [
    'name', 'src', 'other_country', 'country'
  ];


  public function __construct()
  {
    parent::__construct();

    // Used by Auth Token to obtain user info
    Auth::setTokenFields( ['sn', 'user_id'] );
  }


  public function getStringKeyName(){
    return 'user_id';
  }


  public function getSrcAttribute(){
    return app()->config('files.storage.url');
  }


  public function getNameAttribute(){
    $first_name = $this->getAttribute('first_name') . ' ';
    $middle_name = ($n = $this->getAttribute('middle_name')) ? "$n " : '';
    $last_name = $this->getAttribute('last_name');

    pr(['usr' => __FUNCTION__, '$first_name' => $first_name, '$middle_name' => $middle_name, '$last_name' => $last_name]);

    return "{$first_name}{$middle_name}{$last_name}";
  }


  public function getCountryAttribute(){
    $country = $this->getAttribute('country');

    return ($country = stristr($country, 'other_')) ? 'other' : $country;
  }


  public function getOtherCountryAttribute()
  {
    $country = $this->getAttribute('country');

    return ($country = stristr($country, 'other_'))
      ? str_replace('other_', '', $country)
      : $country;
  }




  // ToDo: ========================================================================================

  private function transformInbound($section, $vars){
    switch ($section){
      case 'profile' : {
        if( !empty($vars['country']) and !empty($vars['other_country']) ){
          if($vars['country'] === 'other'){
            $vars['country'] = implode('', [ 'other_', $vars['other_country'] ]);
          }
        }
        break;
      }

      case 'password' : {
        $vars['old_password'] = Arr::make_id('pw', $vars['old_password']);
        $vars['password'] = Arr::make_id('pw', $vars['new_password']);
        break;
      }

      case 'image' : {
        $vars['profile_image'] = $vars['image'];
        $vars['updated_on|v'] = 'now()';
        break;
      }

    }

    return $vars;
  }


  private function getUpdateValues($section, $vars){
    $all_columns = [
      'profile' => [
        'name', 'phone', 'country', 'city'
      ],
      'password' => [
        'password'
      ],
      'image' => [
        'profile_image', 'updated_on'
      ]
    ];
    $columns = $all_columns[ $section ] ?? [];

    $update_values = [];

    foreach ($columns as $column){
      $update_values[ $column ] = $vars[$column];
    }

    return $update_values;
  }


  private function wheres($section, $vars){
    $where = [
      'user_id' => Auth::id(), 'status' => '1'
    ];

    switch ($section){
      case 'password' : {
        $where['password'] = $vars['old_password'];
        break;
      }
    }

    return $where;
  }


  public function update_olds($vars, $section){
    $vars = $this->transformInbound($section, $vars);

    $update_values = $this->getUpdateValues($section, $vars);

    $where = $this->where($section, $vars);

    $limit = "LIMIT 1";

    $report_codes = [
      'profile' => ['01', '01'],
      'password' => ['2', '02'],
      'image' => ['3', '03'],
    ];

    list($success, $error) = $report_codes[ $section ];

    $code = Queries::update_new(User::$table, $update_values, $where, $limit) ? $success : $error;

    $this->result = ['account', $code, []];

    return $this->result[2];
  }


  public function update_profile_image($vars){
    /*$vars = Queries::escape($vars);

    $user_id = $vars['user_id'];
    $image = $vars['image'];

    $table = 'users';
    $update_values = "profile_image = '{$image}', updated_on = now()";
    $where = "WHERE user_id = '$user_id' AND status = '1' LIMIT 1";
    $result = (Queries::update($table, $update_values, $where)) ? '03' : '3';
    $this->result = ['access', $result, []];
    return $this->result[2];*/
    return $this->update_olds($vars, 'image');
  }

  public function get_users($vars = [], $in_class = false){
    $admin_op = $this->run_as_admin($vars);
    $user_id = (isset($vars['user_id'])) ? $admin_op['user_id'] : '';
    $valid_state = (isset($vars['state']) and in_array($vars['state'], [1,2,3]));
    $status = ($valid_state) ? $vars['state'] : '1';

    $table = "users u LEFT JOIN users s ON u.sponsor_id = s.user_id";
    $where = ['u.status' => $status];
    $columns = [
      'u.username', 'u.email', 'u.name', 'u.phone', 'u.created_on',
      'u.activation_mode', 's.username as sponsor', 'u.user_id', 'u.profile_image'
    ];

    $html_table_headers = [
      'Username', 'Email', 'Name', 'Phone', 'Date', 'Mode', 'Sponsor'
    ];
    $html_table_headers = Arr::html_table_headers($html_table_headers, $columns);

    if($user_id){
      $table .= " LEFT JOIN banks b ON u.bank_id = b.bank_id";
      $where = array_merge($where, ['u.user_id' => $user_id]);
      $bank_details = ['u.account_name', 'u.account_number', 'b.bank_id', 'b.bank'];
      $columns = array_merge($columns, $bank_details);
    }

    $users = Queries::select($table, $columns, $where)->to_array();
    if(!$in_class){ array_unshift($users, $html_table_headers); }

    $error_number = ($users) ? '05' : '5';
    $this->result = ['access', $error_number, $users];
    return $this->result[2];
  }

  public function edit_user_bank_details($vars){
    $user_id = $this->authorize_op($vars)['user_id'];
    $table = 'users';
    $columns = ['bank_id', 'account_name', 'account_number'];
    $update_values = [];
    foreach ($columns as $column){ $update_values[$column] = $vars[$column]; }
    $where = ['user_id' => $user_id, 'status' => '1'];

    $error_number = '06';
    if(!Queries::update_new($table, $update_values, $where)){
      $replaces['more_message'] = 'No changes made';
      $error_number = [6, $replaces];
    }
    $this->result = ['access', $error_number, []];
    return $this->result[2];
  }

  public function suspend_or_activate_user($vars){
    $report_index = 'access';  $error_number = 7;
    $replaces = ['more_message' => X_ERROR, 'done' => 'updated'];

    $actions = ['a' => [1,'activated'], 's' => [3,'suspended']];
    $valid_action = (isset($vars['action']) and array_key_exists($vars['action'], $actions));
    if($valid_action){
      list($new_status, $done) = $actions[$vars['action']];
      $states = ['a' => 3, 's' => 1];
      $old_status = $states[$vars['action']];   $vars['state'] = $old_status;

      $replaces = [
        'more_message' => 'User User not on record', 'done' => $done
      ];
      $get_users = $this->get_users($vars, true);
      if($get_users and $user = $get_users[0]){
        $user_id = $user['user_id'];   $username = $user['username'];
        $table = 'users';
        $update_values = ['status' => $new_status];
        $where = ['user_id' => $user_id];   $limit = "LIMIT 1";
        if($suspended = Queries::update_new($table, $update_values, $where, $limit)){
          $error_number = '07';
          $replaces['username'] = $username;
        }
      }
    }
    $error_number = [$error_number, $replaces];
    $this->result = [$report_index, $error_number, []];
    return $this->result[2];
  }


  // =================================================================================================================
  // S E T T I N G S :
  // =================================================================================================================
  protected function retrieve_settings($params){
    if(!is_array($params)){ return null; }

    $table = 'settings';
    $columns = "pid, name, value, sub1, sub2";
    $params = Queries::add_single_quotes($params);
    $params = implode(',', $params);
    $where = "WHERE name IN($params) AND status = 1";

    $settings = [];
    if($rows = Queries::select($table, $columns, $where)->to_array()){
      foreach ($rows as $i => $row){
        if($row['sub2'] != ''){
          $settings[$row['name']][$row['sub1']][$row['sub2']] = $row['value'];
        }elseif($row['sub1'] != ''){
          $settings[$row['name']][$row['sub1']] = $row['value'];
        }else{
          $settings[$row['name']] = $row['value'];
        }
      }
    }
    return $settings;
  }


}


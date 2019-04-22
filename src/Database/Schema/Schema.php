<?php

namespace Orcses\PhpLib\Database\Schema;


use Orcses\PhpLib\Utility\Arr;

class Schema {

  /**
   * Returns [table_name, id_column, model_rows]
   * @param $table
   * @return array
   */
  public static function get($table){
    $parts = static::parts( $table, ['id_column', 'model'] );

    return array_unshift($parts, $table);
  }


  public static function parts($table, $part){
    $schema = static::schema();

    return !empty($schema[ $table ][ $part ]) ? $schema[ $table ][ $part ] : [];
  }


  public static function schema() {
    return static::$schema;
  }


  /**
   * In basic CRUD operations, the 'model' part will always include the id_column by default
   * If 'model' part is not explicitly declared, requesting 'model' will return the
   * full ['create'] part minus the ['guarded'] part
   */
  private static $schema = [
    'users' => [
      'id_column' => 'sn',

      'guarded' => [
        'email', 'password', 'account_number', 'agree', 'captcha', 'updated_on'
      ],

      'model' => [
        'name', 'username', 'sex', 'email'
      ],

      'register' => [
        'name', 'username', 'sex', 'phone', 'email', 'password', 'sponsor_id',
        'country', 'city', 'bank_id', 'account_number', 'account_name', 'agree',
        'package', 'center', 'captcha'
      ],

      'create' => [

      ]
    ],


    'admiral' => [
      'guarded' => [
        'email', 'password', 'updated_on'
      ],
    ]
  ];

  public static function strip_guarded_columns($table, $row){
    $remove_columns = static::tables($table, 'guarded');

    return Arr::drop($remove_columns, $row, true);
  }

}


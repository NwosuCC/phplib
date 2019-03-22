<?php

namespace Orcses\PhpLib;

use Orcses\PhpLib\Incs\HandlesErrors;
use Orcses\PhpLib\Incs\HandlesError;


class Upload implements HandlesErrors {

  use HandlesError;


  private static $error_handler;

  private static $upload_dir, $upload_url, $extensions, $max_size;

  private static $expected_file_type, $allowed_types, $no_exec = true;

  private static $file, $return_temp_file, $result, $more;


  // See HandlesErrors::setErrorHandler() for more info
  public static function setErrorHandler(array $callback = []) {
    static::$error_handler = $callback;
  }

  // See HandlesErrors::getErrorHandler() for more info
  public static function getErrorHandler() {
    return static::$error_handler;
  }


  private static function Slash() {
    return ['BEFORE' => 0, 'AFTER' => -1];
  }


  public static function run($upFile, $parameters, $return_temp_file = false){
    static::$return_temp_file = ($return_temp_file === true);

    if(!$error_number = static::initialize_variables($upFile, $parameters)){
      $error_number = Upload::upload_file();
    }

    return static::end_process($error_number);
  }


  private static function slash_before_dir($path){
    return static::add_leading_slash($path, true);
  }

  private static function slash_before_url($path){
    return static::add_leading_slash($path, false);
  }

  private static function slash_after_dir($path){
    return static::add_trailing_slash($path, true);
  }

  private static function slash_after_url($path){
    return static::add_trailing_slash($path, false);
  }

  private static function add_leading_slash($path, bool $is_dir){
    return static::add_slash($path, $is_dir, static::Slash()['BEFORE']);
  }

  private static function add_trailing_slash($path, bool $is_dir){
    return static::add_slash($path, $is_dir, static::Slash()['AFTER']);
  }

  private static function add_slash($path, bool $is_dir, int $index){
    $path_tree = (array) $path;

    $slash = $is_dir ? DIRECTORY_SEPARATOR : '/';

    $real_path = '';

    foreach ($path_tree as $name) {
      if(substr($name, $index,1) !== $slash){
        $real_path .= ($index === static::Slash()['BEFORE']) ? $slash . $name : $name . $slash;
      }
    }

    return $real_path;
  }

  private static function initialize_variables($upFile, $parameters){
    $values = array_values($parameters);

    list($client, $post_key, $file_name, $upload_directory, $upload_url, $file_type, $allowed_types) = $values;

    $mimes_check = (!empty($file_type) and !empty($allowed_types));

    $required_vars = [ // and their corresponding error_number
      'client' => 1,
      'post_key' => 2,
      'file_name' => 3,
      'mimes_check' => 4,
      'upload_directory' => 11
    ];

    foreach ($required_vars as $var => $number){
      if($var === 'name'){
        if(empty($upFile[$var])){
          $error_number = $number;
          break;
        }
      }
      else{
        if(empty($$var)){
          $error_number = $number;
          break;
        }
      }
    }

    if(!isset($error_number)){
      $upload_directory = static::slash_after_dir( trim($upload_directory) );

      $upload_url = static::slash_after_url( trim($upload_url) );

      $upFile['storage_unique_key'] = $file_name . $post_key . $client;

      static::$upload_dir = $upload_directory;
      static::$upload_url = $upload_url;
      static::$file = $upFile;
      static::$expected_file_type = $file_type;
      static::$allowed_types = $allowed_types;
      static::$extensions = static::getExtensions();
      static::$max_size = static::getAllowedFileSizes();

      $error_number = 0;
    }

    return $error_number;
  }

  private static function end_process($error_number){
    if(!is_int($error_number)){
      $error_number = 10;
    }
    elseif($error_number < 10){
      $error_number = '0' . $error_number;
    }

    $message = ($error_number === '00') ? static::$result : static::$error_messages[$error_number];

    $message = str_replace('{more}', static::$more, $message);

    return [$error_number, $message];
  }

  private static function getExtensions(){
    return [
      'document'=> [
        'txt','pdf','doc','docx','ppt','xls','xlsx','pptx','csv'
      ],
      'image'=> [
        'jpg','png','gif','jpeg'
      ],
      'audio'=> [
        'mp3','aac','wma'
      ],
      'video'=> [
        'mp4','avi','3gp','mpg','wmv'
      ],
      'contact'=> [
        'csv'
      ],
    ];
  }

  private static function getAllowedFileSizes(){
    return [
      'document'=>'2097152', 'image'=>'2097152', 'audio'=>'10485760',
      'contact'=>'750592', 'video'=>'26214400'
    ];
  }

  private static function getMimeType($file) {
    $mime_type = false;
    if (function_exists('finfo_open')) {
      $file_info = finfo_open(FILEINFO_MIME);
      $mime_type = finfo_file($file_info, $file);
      finfo_close($file_info);
    } elseif (function_exists('mime_content_type')) {
      $mime_type = mime_content_type($file);
    }
    return $mime_type;
  }

  private static function isValidMimeType($extension, $file_name){
    $is_valid_extension = (in_array($extension, static::$allowed_types));
    $upFile_mime = static::getMimeType($file_name);
    $expected_mime = static::allMimeTypes($extension);
    $mimes_match = ($upFile_mime and ($upFile_mime === $expected_mime));
    return ($is_valid_extension and $mimes_match);
  }

  private static function getFileType($extension, $file_name){
    $valid_type = (array_key_exists(static::$expected_file_type, static::$extensions));
    $valid_mime = static::isValidMimeType($extension, $file_name);

    if($valid_type and $valid_mime){
      foreach(self::$extensions as $groupType => $allSupportedEXTs){
        if (in_array($extension, $allSupportedEXTs)) { return $groupType; }
      }
    }
    return null;
  }

  private static function formatFileSize($size){
    if($size > pow(1024,2)){
      $size = ($size / pow(1024,2))." MB";
    }elseif($size > 1024){
      $size = ($size / 1024)." kB";
    }else{
      $size = $size." Bytes";
    }
    return $size;
  }

  /*	@file['storage_unique_key'] - concat of 'form inputField name' (e.g 'stLogo') and 'userSession' (e.g $_SESSION['client']).
   *						   Used to form a consistent @_locPath (name of stored file), in case of repeated uploads of same file.
   *	E.g. 	$_FILES[key($_FILES)]['storage_unique_key'] = key($_FILES).$st->thisClient(true);
   *			 	{ $_FILES['stLogo']['storage_unique_key'] = 'stLogo'.$st->thisClient(true); } => @_locPath == 'c4c52bcd18e4a2222' always
   */
  public static function upload_file(){
    $file = static::$file;

    if($file['error'] == 0){

      if($file['name'] != ''){
        $fileName_r = explode('.', $file['name']);
        $extension = strtolower(end($fileName_r));
        $fileType = self::getFileType($extension, $file['tmp_name']);
        $fileType_dir = $fileType;

        if($fileType){
          $allowed_file_size = self::$max_size[$fileType];

          if($file['size'] <= $allowed_file_size){

            if(static::$return_temp_file){
              // Return only the Temp file
              // An example is running 'fputcsv ..' on a temp csv file without storing the file
              static::$result = $file;
              $error_number = 0;
            }
            else{
              if(!$file['storage_unique_key']){
                $file['storage_unique_key'] = md5(microtime());
              }
              $hashed_name = substr(sha1($file['storage_unique_key']),12,23);
              $hashed_file_name = $hashed_name . '.' . $extension;

              $local_path = static::slash_after_dir( static::$upload_dir . $fileType_dir );
              $server_path = static::slash_after_url( static::$upload_url . $fileType_dir );

              $_fileTypePath = static::slash_before_url([$fileType_dir, $hashed_file_name]);

              $_locPath = $local_path . $hashed_file_name;
              $_srvPath = $server_path . $hashed_file_name;

              // Delete existing file then replace with new upload
              if(file_exists($_locPath)){
                unlink($_locPath);
              }

              if(move_uploaded_file($file['tmp_name'], $_locPath)){
                $mode  = chmod($_locPath, 0766);
                // $owner = chown($_locPath, 'root');
                $group = chgrp($_locPath, 'www-data');

                // static::throwError(json_encode(['$_locPath' => $_locPath, '$_srvPath' => $_srvPath]));

                $error_number = 0;
                static::$result = [
                  'name' => $hashed_file_name, 'svp' => $_srvPath, 'ftp' => $_fileTypePath
                ];
              }else{
                $error_number = 15;
              }
            }

          }else{
            $error_number = 14;
            static::$more = "Max size: ".self::formatFileSize($allowed_file_size);
          }

        }else{
          $error_number = 13;
          if(!empty(self::$extensions[$fileType])){
            static::$more = "Supported Types: " . implode(',',self::$extensions[$fileType]);
          }
        }

      }else{
        $error_number = 12;
      }

    }else{
      $error_number = 11;
      static::$more = $file['error'];
    }

    return $error_number;
  }

  private static $error_messages = [
    '01'    => 'Invalid Client ID',
    '02'    => 'Invalid Upload Key',
    '03'    => 'Invalid File',
    '04'    => 'Invalid File format',
    '05'    => 'Invalid Storage Directory',

    '10'    => 'An unexpected error occurred',

    '11'    => "File Error: {more}",
    '12'    => "Trying to upload an empty file",
    '13'    => "File type is not supported. {more}",
    '14'    => "File size limit exceeded. {more}",
    '15'    => "File not saved."
  ];

  public static function allMimeTypes($extension){
    $all_mimes = [
      'jpg'   => 'image/jpeg; charset=binary',
      'jpeg'  => 'image/jpeg; charset=binary',
      'png'   => 'image/png; charset=binary',
      'gif'   => 'image/gif; charset=binary',

      'mp3'   => 'audio/mpeg; charset=binary',
      'aac'   => 'audio/x-hx-aac-adts; charset=binary',

      'mp4'   => 'video/mp4; charset=binary',
      'mpeg'  => 'video/mpeg; charset=binary',
    ];
    if($extension){
      return (array_key_exists($extension, $all_mimes)) ? $all_mimes[$extension] : null;
    }
    return $all_mimes;
  }

}
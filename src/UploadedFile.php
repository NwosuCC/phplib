<?php

namespace Orcses\PhpLib;


use SplFileInfo;
use Orcses\PhpLib\Interfaces\Uploadable;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;


class UploadedFile extends SplFileInfo implements Uploadable
{
  /** A list of allowed (expected) extensions for the file */
  protected $expected_extensions;

  protected $error, $file, $name, $tmp_name, $mime_type, $extension, $size;

  protected $short_mime_type, $file_category, $formatted_size;

  protected $unique_id, $storage_name, $disk, $config, $permissions, $mode, $group;


  public function __construct($file, array $options = [])
  {
    $this->file = $file;

    if($this->file['error'] !== UPLOAD_ERR_OK){
      $this->abort(11);
    }

    $this->parseOptions( $options );

    parent::__construct( $this->tmpName() );

    $this->initializeFIleProps();
  }


  protected function config(string $key)
  {
    $this->config[ $key ] = app()->config("files.uploads.{$key}");

    return $this->config[ $key ];
  }


  public function getConfig(string $key = null)
  {
    return array_key_exists($key, $this->config) ? $this->config[ $key ] : $this->config;
  }


  protected function parseOptions(array $options)
  {
    $this->expected_extensions = $options['extensions'] ?? [];
  }


  protected function initializeFIleProps()
  {
    $illegal = array_merge(
      array_map('chr', range(0, 31)), ['<', '>', ':', '"', '/','\\', '|', '?', '*', ' ']
    );

    if( ! $file_name = str_replace($illegal, '-', $this->file['name'])){
      $this->abort(12);
    }

    $file_path_into = pathinfo( $file_name );

    $this->name = $file_path_into['filename'] ?: '';

    $this->extension = strtolower($file_path_into['extension'] ?: '');

    $this->size = $this->getSize();
  }


  /* Will not have unique_id */
  public function storeAs(string $name, string $disk, array $permissions = null)
  {
    $this->unique_id = null;

    $this->storage_name = $name;

    $this->store($disk, $permissions);
  }


  public function store(string $disk, array $permissions = null)
  {
    $local_file = $this->localFile($disk);

    $config_allow_overwrite_disks = $this->config('write_mode.replace');

    // Delete existing file then replace with new uploaded file
    if(file_exists($local_file) && in_array($disk, $config_allow_overwrite_disks)){
      unlink($local_file);
    }

    if( ! move_uploaded_file( $this->tmpName(), $local_file)){
      $this->abort(15);
    }

    if($this->permissions = $permissions){

      if(array_key_exists('mode', $permissions)){
        $this->mode = $permissions['mode'];

        chmod($local_file, $this->mode);
      }

      if(array_key_exists('group', $permissions)){
        $this->group = $permissions['group'];

        chgrp($local_file, $this->group);
      }
    }

    return true;
  }


  public function localFile(string $disk)
  {
    $upload_dir = $this->config("disks.{$disk}.dir");

    $config_categorize_disks = $this->config('write_mode.categorize');

    if( ! $upload_dir){
      $this->abort(16);
    }

    $this->disk = $disk;

    if(in_array($disk, $config_categorize_disks)){
      $upload_dir .= '/' . $this->category();
    }

    pr(['usr' => __FUNCTION__, '$disk' => $disk, '$upload_dir' => $upload_dir, 'base_dir()' => base_dir()]);

    return real_dir(
      base_dir() . $upload_dir .'/'. $this->storageName() .'.'. $this->extension()
    );
  }


  public function disk()
  {
    return $this->disk;
  }


  public function storageName()
  {
    if( ! $this->storage_name){
      $this->storage_name = $this->uniqueId();
    }

    return $this->storage_name;
  }


  public function uniqueId()
  {
    if( ! $this->unique_id){

      $id_params = $this->name() . 'post_key' . auth()->id();

      $this->unique_id = substr( sha1( $id_params ),3,31);
    }

    return $this->unique_id;
  }


  public function rawFile()
  {
    return $this->file;
  }


  public function name()
  {
    return $this->name;
  }


  public function tmpName()
  {
    if( ! $this->tmp_name){
      $this->tmp_name = $this->file['tmp_name'];
    }

    return $this->tmp_name;
  }


  public function extension()
  {
    return $this->extension;
  }


  public function fullMimeType()
  {
    if( ! $this->mime_type){

      if (class_exists('finfo')) {
        $this->mime_type = (new \finfo())->file($this->file['tmp_name'],  FILEINFO_MIME);

      }
      elseif (function_exists('finfo_open')) {

        $file_info = finfo_open(FILEINFO_MIME);

        $this->mime_type = finfo_file($file_info, $this->file['tmp_name']);

        finfo_close($file_info);

      }
      elseif (function_exists('mime_content_type')) {

        $this->mime_type = mime_content_type( $this->file );
      }
    }

    return $this->mime_type;
  }


  public function shortMimeType()
  {
    if( ! $this->short_mime_type && $this->fullMimeType()){
      $this->short_mime_type = trim( explode(';', $this->mime_type)[0] );
    }

    return $this->short_mime_type;
  }


  public function hasValidMimeType(array $allowed_extensions)
  {
    $allowed_extensions = array_map('strtolower', $allowed_extensions);

    $extension = strtolower( $this->extension() );

    $expected_mime = $this->commonMimeTypes( $extension );

    if( ! $is_valid_extension = (in_array($extension, $allowed_extensions))){
      return false;
    }

    $mime_type = $this->fullMimeType();

    return ($mime_type && ($mime_type === $expected_mime));
  }


  /**
   * Returns a 'human-readable' form of the mime type in Dot Notation
   * Can be useful in categorising files in folders or database
   *
   *@param bool $short  If true, returns only the first part. Default is false
   *@return string

   * E.g, for mime_type 'image/pnf', getFileCategory(true) (ie, short-form) returns 'image'
   */
  public function category(bool $short = true)
  {
    if( ! $this->file_category){

      $mime_type = $this->shortMimeType();

      if($parts_0 = explode('/', $mime_type)){

        $parts_1 = explode('.', $parts_0[1]);

        if(count($parts_0) === 2 && count($parts_1) === 1){

          if($short && $parts_0[0] === 'application'){
            return $this->file_category = $parts_0[1];
          }

          return $this->file_category = $short ? $parts_0[0] : implode('.', $parts_0);
        }

        if(stripos($parts_0[1], 'officedocument') !== false){

          return $this->file_category = $short ? end($parts_1) : "document." . end($parts_1);
        }

      }
    }

    return $this->file_category;
  }


  public function size()
  {
    return $this->size;
  }


  /** @return array */
  public function formattedSize()
  {
    if( ! $this->formatted_size){
      $this->formatted_size = $this->fileSizeFromBytes( $this->size() );
    }

    return $this->formatted_size;
  }


  // E.g $bytes : int returned from $this->getSize()
  public function fileSizeFromBytes(int $bytes)
  {
    $units = [
      0 => 'Bytes', 1 => 'kB', 2 => 'MB', 3 => 'GB', 4 => 'TB'
    ];

    $n = 0;
    while($bytes >= pow(1024, ++$n)){}

    $size = number_format( $bytes / pow(1024, ($n - 1)), 2);

    $unit = $units[ $n - 1 ];

    return [(float) $size, $unit];
  }


  // E.g $size : [2, 'M'] for 2MB; [500, 'K'] for 500kB
  public function fileSizeToBytes(array $size)
  {
    $nth = ['K' => 1, 'M' => 2, 'G' => 3, 'T' => 4];

    if($args_count = count($size) !== 2 || ! array_key_exists($size[1], $nth)){

      throw new InvalidArgumentException( implode(',', $size), __FUNCTION__);
    }

    [$number, $unit] = $size;

    return $number * pow(1024, $nth[ $unit ]);
  }


  public function error()
  {
    return $this->error;
  }


  protected function abort(int $code)
  {
    $error_messages = [
      '11'    => "File Error: {$this->file['error']}",
      '12'    => "File name is empty",
//      '13'    => "File type is not supported. {more}",
//      '14'    => "File size limit exceeded. {more}",
      '15'    => "File not saved.",
      '16'    => 'Storage disk/directory could not be accessed',
    ];

    $this->error = $error_messages[ $code ] ?? $code;

    throw new InvalidArgumentException("Uploaded file error: {$this->error}");
  }


  // ToDo: allow the developer to extend this list for use in validation
  public function commonMimeTypes(string $extension = null)
  {
    $common_mimes = [
      'jpg'   => 'image/jpeg; charset=binary',
      'jpeg'  => 'image/jpeg; charset=binary',
      'png'   => 'image/png; charset=binary',
      'gif'   => 'image/gif; charset=binary',

      'mp3'   => 'audio/mpeg; charset=binary',
      'aac'   => 'audio/x-hx-aac-adts; charset=binary',

      'mp4'   => 'video/mp4; charset=binary',
      'mpeg'  => 'video/mpeg; charset=binary',

      'txt'  => 'text/plain; charset=us-ascii',

      'docx'  => 'application\/vnd.openxmlformats-officedocument.presentationml.presentation'
    ];

    if($extension){
      return array_key_exists($extension, $common_mimes) ? $common_mimes[ $extension ] : null;
    }

    return $common_mimes;
  }

}
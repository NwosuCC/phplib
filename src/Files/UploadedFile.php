<?php

namespace Orcses\PhpLib\Files;


use Orcses\PhpLib\Interfaces\Uploadable;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;


class UploadedFile extends File implements Uploadable
{
  /** A list of allowed (expected) extensions for the file */
  protected $expected_extensions;

  protected $file = [], $tmp_name;

  protected $unique_id, $storage_name, $disk, $config, $permissions, $mode, $group;


  public function __construct(array $file, array $options = [])
  {
    $this->file = $file;

    if($this->file['error'] !== UPLOAD_ERR_OK){
      $this->abort(11);
    }

    $this->parseOptions( $options );

    $file_name = $this->sanitizeName();

    parent::__construct( $this->tmpName(), $file_name );
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


  protected function sanitizeName()
  {
    $illegal = array_merge(
      array_map('chr', range(0, 31)), ['<', '>', ':', '"', '/','\\', '|', '?', '*', ' ']
    );

    if( ! $file_name = str_replace($illegal, '-', $this->file['name'])){
      $this->abort(12);
    }

    return $file_name;
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


  public function tmpName()
  {
    if( ! $this->tmp_name){
      $this->tmp_name = $this->file['tmp_name'];
    }

    return $this->tmp_name;
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
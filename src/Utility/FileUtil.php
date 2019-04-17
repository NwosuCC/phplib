<?php

namespace Orcses\PhpLib\Utility;


class FileUtil
{

  protected static $error;


  public static function getError()
  {
    $error = static::$error;

    return (static::$error = null) ?: $error;
  }


  public static function loadResource(string $file_path)
  {
    if( is_file($file_path) && ! $exists = file_exists( $file_path )){

      static::$error = "File not found";

      return null;
    }

    return file_get_contents( $file_path );
  }


  public static function loadJsonResource(string $file_path, bool $assoc = true)
  {
    if( ! $contents = static::loadResource( $file_path )){
      return null;
    }

    if( ! $decoded_contents = Str::safeJsonDecode( $contents, $assoc )){

      static::$error = Str::getError();
    }

    return $decoded_contents;
  }


  /**
   * @author      Aidan Lister <aidan@php.app>
   * @version     1.0.1
   * @link        http://aidanlister.com/2004/04/recursively-copying-directories-in-php
   * @param       string   $source        Source path
   * @param       string   $destination   Destination path
   * @param       array    $permissions   Unix file permissions to apply to the new copied directories
   * @return      bool     Returns TRUE on success, FALSE on failure
   */
  public static function copy_recursive($source, $destination, $permissions = []){
    // Check for symlinks
    if (is_link($source)) { return symlink(readlink($source), $destination); }

    // Simple copy for a file
    if (is_file($source)) { return copy($source, $destination); }

    // Make destination directory
    if (!is_dir($destination)) {
      mkdir($destination);
      if(!empty($permissions['group'])){ chgrp($destination, $permissions['group']); }
      if(!empty($permissions['mode'])){ chmod($destination, $permissions['mode']); }
    }

    // Loop through the folder
    $dir = dir($source);
    while (false !== ($entry = $dir->read())) {
      // Skip pointers
      if ($entry == '.' || $entry == '..') { continue; }

      // Deep copy directories
      static::copy_recursive("$source/$entry", "$destination/$entry");
    }

    // Clean up
    $dir->close();
    return true;
  }

}


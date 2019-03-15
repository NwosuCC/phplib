<?php

namespace Orcses\PhpLib\Utility;


class FileSystemCopy
{
  /**
   * @author      Aidan Lister <aidan@php.net>
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


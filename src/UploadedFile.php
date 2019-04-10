<?php

namespace Orcses\PhpLib;


use Orcses\PhpLib\Exceptions\InvalidArgumentException;
use SplFileInfo;


class UploadedFile extends SplFileInfo
{
  /** A list of allowed (expected) extensions for the file */
  protected $expected_extensions;

  protected $file, $name, $tmp_name, $mime_type, $extension;

  protected $short_mime_type, $file_category, $formatted_size;


  public function __construct($file, array $options = [])
  {
    $this->file = $file;

    $this->parseOptions( $options );

    $this->initializeFIleProps();

    parent::__construct( $this->tmpName() );
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

    $file_name = str_replace($illegal, '-', $this->file['name']);

    $file_path_into = pathinfo( $file_name );

    $this->name = $file_path_into['filename'] ?: '';

    $this->extension = $file_path_into['extension'] ?: '';
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
    $extension = $this->extension();

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
  public function fileCategory(bool $short = true)
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


  /** @return array */
  public function formattedSize()
  {
    if( ! $this->formatted_size){
      $this->formatted_size = $this->fileSizeFromBytes( $this->getSize() );
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
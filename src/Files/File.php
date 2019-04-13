<?php

namespace Orcses\PhpLib\Files;


use SplFileInfo;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;


class File extends SplFileInfo
{
  protected $name, $mime_type, $extension, $size;

  protected $short_mime_type, $file_category, $formatted_size;

  protected $error, $file_path;


  public function __construct(string $file_path, string $file_name = null)
  {
    $this->file_path = $file_path;

    parent::__construct( $this->file_path );

    $this->initializeFIleProps( $file_name );
  }


  protected function initializeFIleProps(string $file_name)
  {
    $file_name = $file_name ?: $this->file_path;

    $file_path_into = pathinfo( $file_name );

    $this->name = $file_path_into['filename'] ?: '';

    $this->extension = strtolower($file_path_into['extension'] ?: '');

    $this->size = $this->getSize();
  }


  public function name()
  {
    return $this->name;
  }


  public function extension()
  {
    return $this->extension;
  }


  public function fullMimeType()
  {
    if( ! $this->mime_type){

      if (class_exists('finfo')) {

        $this->mime_type = (new \finfo())->file($this->file_path,  FILEINFO_MIME);

      }
      elseif (function_exists('finfo_open')) {

        $file_info = finfo_open(FILEINFO_MIME);

        $this->mime_type = finfo_file($file_info, $this->file_path);

        finfo_close($file_info);

      }
      elseif (function_exists('mime_content_type')) {

        $this->mime_type = mime_content_type( $this->file_path );
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



}
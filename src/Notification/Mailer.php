<?php

namespace Orcses\PhpLib\Notification;


use Swift_Mailer;
use Swift_Message;
use Swift_Attachment;
use Swift_SmtpTransport;
use Swift_TransportException;
use Swift_RfcComplianceException;

use Exception;
use Orcses\PhpLib\Files\File;
use Orcses\PhpLib\Interfaces\Mailable;
use Orcses\PhpLib\Exceptions\InvalidFileException;
use Orcses\PhpLib\Exceptions\FileNotFoundException;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;


class Mailer implements Mailable
{

  protected $email, $name, $subject, $sender_name, $message;

  protected $html_content, $text_content, $attachment = [], $link;

  protected $sender = [], $recipient = [], $error;


  public function __construct()
  {
    $this->setCredentials();
  }


  public function __get(string $key)
  {
    return property_exists($this, $key) ? $this->{$key} : null;
  }


  public function mail(array $to = null, string $subject = null, string $body = null)
  {
    $this->to( ...($to ?: []) );

    $this->subject( $subject );

    $this->html( $body );

    return $this;
  }


  // ToDo: For different sender names, use 'sendAs()' ??, or add config 'mail_from' => [] ???
  public function senderName(string $name = null)
  {
    $this->sender_name = $name;

    return $this;
  }


  public function to(string $email, string $name = null)
  {
    $this->email = $email;

    $this->name = $name ?: '';

    return $this;
  }


  public function subject(string $subject)
  {
    $this->subject = $subject;

    return $this;
  }


  public function attachment(string $file_path)
  {
    if($attachment_file = $this->verifyFile($file_path)){

      $this->attachment[] = Swift_Attachment::fromPath($attachment_file);
    }

    return $this;
  }


  public function html(string $html_content)
  {
    $this->html_content = $html_content;

    return $this;
  }


  public function htmlFile(string $file_path)
  {
    if($html_file = $this->verifyFile($file_path, 'html')){

      $this->html_content = file_get_contents($html_file);
    }

    return $this;
  }


  public function text(string $text_content)
  {
    $this->text_content = $text_content;

    return $this;
  }


  public function textFile(string $file_path)
  {
    if($html_file = $this->verifyFile($file_path, 'txt')){

      $this->text_content = file_get_contents($html_file);
    }

    return $this;
  }


  protected function verifyFile(string $filename, string $format = null)
  {
    $file_type = 'Mail Content';

    if( ! file_exists($filename)){

      throw new FileNotFoundException($file_type, $filename);
    }

    if($format){
      $file_obj = new File($filename);

      if(strtolower($format) !== strtolower($file_obj->extension())){

        throw new InvalidFileException($filename, $file_type);
      }
    }

    return $filename;
  }


  protected function setCredentials()
  {
    $default = app()->config('mail.default');

    if( ! $mail = app()->config("mail.drivers.{$default}")){
      throw new InvalidArgumentException(
        "Mail credentials for '{$default}' could not be loaded"
      );
    }


  }


  public function error()
  {
    $error = $this->error;

    return ($this->error = null) ?: $error;
  }


  public function compileError(string $error)
  {
    $sender = "['" . current($this->sender) ."': ". key($this->sender) . "]";

    $recipient = "['" . current($this->recipient) ."': ". key($this->recipient) . "]";

    $attempt_info = "{$sender} attempted send to {$recipient}";

    $this->error = get_called_class() .' - '. $error .' - '. $attempt_info;
  }


}

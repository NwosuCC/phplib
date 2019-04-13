<?php

namespace Orcses\PhpLib\Notification;


use Swift_Mailer;
use Swift_Message;
use Swift_Attachment;
use Swift_SmtpTransport;
use Swift_TransportException;

use Exception;
use Orcses\PhpLib\Files\File;
use Orcses\PhpLib\Interfaces\Mailable;
use Orcses\PhpLib\Exceptions\InvalidFileException;
use Orcses\PhpLib\Exceptions\FileNotFoundException;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;


class Mailer implements Mailable
{
  protected $host, $port, $security, $username, $password;

  protected $email, $name, $subject, $sender_name, $message;

  protected $html_content, $text_content, $attachment = [], $link;

  protected $sender = [], $recipient = [], $error;


  public function __construct()
  {
    $this->setCredentials();
  }


  public function mail(array $to = null, string $subject = null, string $body = null)
  {
    $this->to( ...($to ?: []) );

    $this->subject( $subject );

    $this->html( $body );

    return $this;
  }


  public function send()
  {
    $this->compose();

    try {
      return ($mailer = $this->createTransport())
        ? !! $mailer->send($this->message)
        : false;
    }
    catch(Swift_TransportException $e){}
    catch(Exception $e){}

    $this->compileError( $e->getMessage() );

    return false;
  }


  protected function compose()
  {
    $this->sender = [$this->username => $this->sender_name ?: ''];

    $this->recipient = [$this->email => $this->name ?: ''];
    pr(['usr' => __FUNCTION__, 'sender' => $this->sender, 'recipient' => $this->recipient]);

    $this->message = (new Swift_Message())

      ->setSubject( $this->subject )

      ->setFrom( $this->sender )

      ->setTo( $this->recipient )

      ->setBody($this->html_content, 'text/html')

      ->addPart($this->text_content, 'text/plain');

    foreach($this->attachment as $attachment){

      $this->message->attach($attachment);
    }
  }


  protected function createTransport()
  {
    $transport = new Swift_SmtpTransport($this->host, $this->port, $this->security);

    $transport
      ->setUsername($this->username)
      ->setPassword($this->password);

    return new Swift_Mailer($transport);
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

    $this->host     = $mail['host'];

    $this->port     = $mail['port'];

    $this->security = $mail['encryption'];

    $this->username = $mail['username'];

    $this->password = $mail['password'];
  }


  public function error()
  {
    $error = $this->error;

    return ($this->error = null) ?: $error;
  }


  public function compileError(string $error)
  {
    pr(['usr' => __FUNCTION__, 'sender 000' => $this->sender, 'recipient' => $this->recipient]);
    $sender = '[' . key($this->sender) .' '. current($this->sender) . ']';

    $recipient = '[' . implode(',', $this->recipient) . ']';
    pr(['usr' => __FUNCTION__, 'sender 111' => $sender, 'recipient' => $recipient]);

    $attempt_info = "{$sender} attempted send to {$recipient}";

    $this->error = [get_called_class(), $error . ' ' . $attempt_info];
  }


}

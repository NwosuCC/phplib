<?php

namespace Orcses\PhpLib\Notification;


use Orcses\PhpLib\Files\File;
use Orcses\PhpLib\Interfaces\Mail\Mailable;
use Orcses\PhpLib\Interfaces\Mail\MailClient;
use Orcses\PhpLib\Exceptions\InvalidArgumentException;
use Orcses\PhpLib\Exceptions\Files\InvalidFileException;
use Orcses\PhpLib\Exceptions\Base\FileNotFoundException;
use Orcses\PhpLib\Utility\Arr;


class Mailer implements Mailable
{
  protected $mail_client;

  protected $credentials = [];

  protected $email, $name, $subject, $sender_name, $message;

  protected $html_content, $text_content, $attachment = [], $link;

  protected $reply_to, $cc = [], $bcc = [];

  protected $sender = [], $recipient = [], $error;


  public function __construct(MailClient $mail_client)
  {
    $this->setCredentials();

    $this->mail_client = $mail_client;
  }


  public function send()
  {
    if( ! $sent = $this->mail_client->send( $this )){

      $this->error = $this->mail_client->getError();

      $this->compileError();
    }

    return $sent;
  }


  public function getMailObject()
  {
    return [
      'credentials' => $this->credentials,

      'mailable' => [
        'sender_name' => $this->sender_name ?: '',
        'reply_to' => $this->reply_to ?: '',
        'email' => $this->email,
        'name' => $this->name,
        'cc' => array_unique( $this->cc ),
        'bcc' => array_unique( $this->bcc ),
        'subject' => $this->subject,
        'html_content' => $this->html_content,
        'text_content' => $this->text_content,
        'attachment' => $this->attachment,
      ]
    ];
  }


  public function mail(array $to = null, string $subject = null, string $body = null)
  {
    $this->to( ...($to ?: []) );

    $this->subject( $subject );

    $this->html( $body );

    return $this;
  }


  public function senderName(string $name)
  {
    $this->sender_name = $name;

    return $this;
  }


  public function replyTo(string $email)
  {
    $this->reply_to = $email;

    return $this;
  }


  public function cc(array $emails)
  {
    $this->cc = array_merge( $this->cc, $emails );

    return $this;
  }


  public function bcc(array $emails)
  {
    $this->bcc = array_merge( $this->bcc, $emails );

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

      $this->attachment[] = $attachment_file;
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

    if( ! $credentials = app()->config("mail.drivers.{$default}")){

      throw new InvalidArgumentException(
        "Mail credentials for '{$default}' could not be loaded"
      );
    }

    $this->credentials = $credentials;
  }


  public function getError()
  {
    $error = $this->error;

    return ($this->error = null) ?: $error;
  }


  public function compileError()
  {
    $error = $this->getError();

    [$sender_email, $sender_name] = Arr::keyValue( $this->sender );

    $sender = "['{$sender_name}': {$sender_email}]";

    [$recipient_email, $recipient_name] = Arr::keyValue( $this->recipient );

    $recipient = "['{$recipient_email}': {$recipient_name}]";

    $attempt_info = "{$sender} attempted send to {$recipient}";

    $this->error = get_called_class() .' - '. $error .' - '. $attempt_info;
  }


}

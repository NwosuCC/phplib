<?php

namespace Orcses\PhpLib\Notification\Clients;


use Exception;
use Orcses\PhpLib\Utility\Arr;
use Orcses\PhpLib\Interfaces\Mail\Mailable;
use Orcses\PhpLib\Interfaces\Mail\MailClient;


class Mail implements MailClient
{
  protected $host, $port, $security, $username, $password;

  protected $mail, $message, $error;


  public function send(Mailable $mailable)
  {
    $this->mail = $mailable->getMailObject();

    $this->configure();

    list( $to, $subject, $html_body, $headers ) = $this->compose();

    try {
      return !! mail( $to, $subject, $html_body, $headers );
    }
    catch(Exception $e){}

    if( ! empty($e)){
      $this->error = $e->getMessage();
    }

    return false;
  }


  protected function compose()
  {
    $mail = $this->mail['mailable'];

    $attachment   = $mail['attachment'];

    $to      = $mail['email'];
    $subject = $mail['subject'];
    $content = $mail['html_content'] ?: $mail['text_content'];

    $headers = $this->getHeaders();

    return [$to, $subject, $content, $headers];
  }


  protected function getHeaders()
  {
    $mail = $this->mail['mailable'];

    $cc = implode(',', Arr::stripEmpty( $mail['cc'] ) );

    $bcc = implode(',', Arr::stripEmpty( $mail['bcc'] ) );

    $headers = [
      "From: {$mail['sender_name']} <{$this->username}>",
      "Reply-To: {$mail['reply_to']}",
      "cc: {$cc}",
      "bcc: {$bcc}",
      "Date: " . date('M d Y H:i:s'),
      "X-Mailer: PHP/" . PHP_VERSION,
      "MIME-Version: 1.0",
    ];

    if( ! $mail['attachment']){
      $type = $mail['html_content'] ? 'text/html' : 'text/plain';

      $headers[] =  "Content-Type: {$type}; charset=UTF-8";
    }

    return implode("\r\n", $headers);
  }


  protected function configure()
  {
    $credentials    = $this->mail['credentials'];

    $this->host     = $credentials['host'];

    $this->port     = $credentials['port'];

    $this->security = $credentials['encryption'];

    $this->username = $credentials['username'];

    $this->password = $credentials['password'];
  }


  public function getError()
  {
    return $this->error;
  }

}

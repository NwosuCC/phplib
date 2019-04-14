<?php

namespace Orcses\PhpLib\Notification\Clients;


use Orcses\PhpLib\Interfaces\Mailable;
use Swift_Mailer;
use Swift_Message;
use Swift_Attachment;
use Swift_SmtpTransport;
use Swift_TransportException;
use Swift_RfcComplianceException;

use Exception;


class Swift
{
  protected $host, $port, $security, $username, $password;

  protected $mail, $message, $error;


  public function configure(array $credentials)
  {
    $this->host     = $credentials['host'];

    $this->port     = $credentials['port'];

    $this->security = $credentials['encryption'];

    $this->username = $credentials['username'];

    $this->password = $credentials['password'];
  }


  protected function compose(Mailable $mail)
  {
    $sender = [$mail->{'username'} => $mail->{'sender_name'} ?: ''];

    $recipient = [$mail->{'email'} => $mail->{'name'} ?: ''];

    try {
      $this->message = (new Swift_Message())
        ->setSubject( $mail->{'subject'} )
        ->setFrom( $sender )
        ->setTo( $recipient )
        ->setBody($mail->{'html_content'}, 'text/html')
        ->addPart($mail->{'text_content'}, 'text/plain');

      foreach($this->attachment as $attachment){
        $this->message->attach($attachment);
      }

      return true;
    }
    catch(Swift_RfcComplianceException $e){
      $error = 'Please, ensure you have MAIL_SERVER settings in .env and config/app.php files.';

      $error .= ' Swift_RfcComplianceException: ' . $e->getMessage();
    }
    catch(Exception $e){
      $error = $e->getMessage();
    }

    if( ! empty($error)){
      $this->error = $error;
    }

    return null;
  }


  public function send()
  {
    try {
      return $this->message && ($mailer = $this->createTransport())

        ? !! $mailer->send($this->message)

        : false;
    }
    catch(Swift_TransportException $e){}
    catch(Exception $e){}

    if( ! empty($e)){
      $this->error = $e->getMessage();
    }

    return false;
  }


  protected function createTransport()
  {
    $transport = new Swift_SmtpTransport($this->host, $this->port, $this->security);

    $transport
      ->setUsername($this->username)
      ->setPassword($this->password);

    return new Swift_Mailer($transport);
  }


  public function getError()
  {
    return $this->error;
  }

}

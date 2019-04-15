<?php

namespace Orcses\PhpLib\Notification\Clients;


use Swift_Mailer;
use Swift_Message;
use Swift_Attachment;
use Swift_SmtpTransport;
use Swift_TransportException;
use Swift_RfcComplianceException;

use Exception;
use Orcses\PhpLib\Interfaces\Mail\Mailable;
use Orcses\PhpLib\Interfaces\Mail\MailClient;


class Swift implements MailClient
{
  protected $host, $port, $security, $username, $password;

  protected $mail, $message, $error;


  public function send(Mailable $mailable)
  {
    $this->mail = $mailable->getMailObject();

    $this->configure();

    try {
      return $this->compose() && ($mailer = $this->createTransport())

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


  protected function compose()
  {
    $mail = $this->mail['mailable'];

    $sender = [$this->username => $mail['sender_name'] ?: ''];

    $recipient = [$mail['email'] => $mail['name'] ?: ''];

    $subject      = $mail['subject'];
    $html_content = $mail['html_content'];
    $text_content = $mail['text_content'];
    $attachment   = $mail['attachment'];

    try {
      $this->message = (new Swift_Message())
        ->setSubject( $subject )
        ->setFrom( $sender )
        ->setTo( $recipient )
        ->setBody( $html_content, 'text/html')
        ->addPart( $text_content, 'text/plain');

      foreach($attachment as $file_path){

        $this->message->attach( Swift_Attachment::fromPath($file_path) );
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


  protected function createTransport()
  {
    $transport = new Swift_SmtpTransport( $this->host, $this->port, $this->security );

    $transport
      ->setUsername( $this->username )
      ->setPassword( $this->password );

    return new Swift_Mailer($transport);
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

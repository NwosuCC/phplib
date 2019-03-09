<?php

namespace Orcses\PhpLib;

use Exception;
use Swift_Attachment;
use Swift_Mailer;
use Swift_Message;
use Swift_SmtpTransport;

//require_once __DIR__ . '/../vendor/swiftmailer/swiftmailer/lib/swift_required.php';


class Mailer {
  private $server, $port, $security, $username, $password;
  private $file_types, $category, $recipient;
  private $date, $from, $subject, $html_content, $plain_text, $message, $attachment, $link;
  private $error;

  private $mail_categories = [
    'reg' => [
      'subj' => 'Account Verification',
      'label' => '',
      'html_template' => '',
      'text_template' => ''
    ],
    'psw' => [
      'subj' => 'Password Recovery',
      'label' => '',
      'html_template' => '',
      'text_template' => ''
    ],
    'com' => [
      'subj' => 'Comments and Reviews Download',
      'label' => 'Commpro',
      'html_template' => 'templates/body.html',
      'text_template' => ''
    ],
  ];

  public function __construct($category, $date){
    if(!empty($category)){
      $this->category = $category; }else{ return null;
    }
    $this->date = $date;
  }

  public function sendMail($email, $name, $attachment = '', $link=''){
    $this->init_mailer()
      ->compose($email, $name, $attachment, $link)
      ->createMessage();
    return ($mailer = $this->createTransport()) ? $mailer->send($this->message) : false;
  }

  private function init_mailer(){
    $this->file_types = [
      'h' => ['html', 'htm'], 't' => ['txt']
    ];
    $this->setCredentials();
    return $this;
  }

  private function setCredentials(){
    $this->username = $_ENV['mail_user'];
    $this->server   = $_ENV['mail_server'];
    $this->port     = $_ENV['mail_port'];
    $this->security = $_ENV['mail_security'];
    $this->password = $_ENV['mail_password'];
  }

  private function compose($email, $name, $attachment, $link){
    $this->recipient = [$email => $name];
    $categories = $this->mail_categories;

    if(array_key_exists($this->category,$categories)){
      $this->subject = $categories[$this->category]['subj'];

      $this->from = [
        $this->username => $categories[$this->category]['label']
      ];

      $this->setHtmlContent(
        $categories[$this->category]['html_template'], $link
      );

      $this->setPlainText(
        $categories[$this->category]['text_template'], $name, $link
      );

      $this->addAttachment($attachment);

    }else{
      $this->setError(['m13', "Mail Category not supported"]);
    }

    return $this;
  }

  private function createMessage(){
    $this->message = Swift_Message::newInstance()
      ->setSubject($this->subject)
      ->setFrom($this->from)
      ->setTo($this->recipient)
      ->setBody($this->html_content, 'text/html')
      ->addPart($this->plain_text, 'text/plain');

    if($this->attachment){
      $this->message->attach($this->attachment);
    }
  }

  private function createTransport(){
    $transport = Swift_SmtpTransport::newInstance($this->server, $this->port, $this->security)
      ->setUsername($this->username)
      ->setPassword($this->password);
    return Swift_Mailer::newInstance($transport);
  }

  private function setHtmlContent($htm_file, $link){
    if($htm_file != '' and $this->verifyFile($htm_file,'h')){
      $html_content = file_get_contents($htm_file);
      $html_content = str_replace('{link}', $link, $html_content);
      $this->html_content = $html_content;
    }
  }

  private function setPlainText($txt_file, $name, $link){
    if($txt_file != '' and $file = $this->verifyFile($txt_file, 't')){
      $text = file_get_contents($file);
      $text = str_replace('{name}', $name, $text);
      $text = str_replace('{date}', $this->date, $text);
      $text = str_replace('{link}', $link, $text);
      $this->plain_text = $text;
    }
  }

  private function addAttachment($file_to_attach){
    if($file_to_attach != '' and file_exists($file_to_attach)){
      $this->attachment = Swift_Attachment::fromPath($file_to_attach);
    }
  }

  private function verifyFile($filename,$group){
    if(file_exists($filename)){
      $file_parts = explode('.',$filename);
      $extension = (is_array($file_parts)) ? strtolower(end($file_parts)) : '';
      if(in_array($extension, $this->file_types[$group])){ return true; }
      else{ $this->setError(['m12', "Invalid file type"]); }
    }else{
      $this->setError(['m11', "File {$filename} not found"]);
    }
    return null;
  }

  private function setError($error) {
    list($error_code, $error_message) = $error;
    if(empty($error_message)){ $error_message = 'Operation failed!'; }
    $this->error = "[$error_code] " . $error_message;

    throw new Exception('Error: ' . $this->error);
  }

  public function getError(){
    $error = $this->error;   $this->error = null;
    return $error;
  }

}

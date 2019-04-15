<?php

namespace Orcses\PhpLib\Interfaces\Mail;


interface Mailable
{

  /**
   * @return array Mailable object
   *
   * Example:
   *  $mailable = [
        'credentials' => $this->credentials,

        'mail' => [
          'sender_name' => $this->sender_name,
          'email' => $this->email,
          'name' => $this->name,
          'subject' => $this->subject,
          'html_content' => $this->html_content,
          'text_content' => $this->text_content,
          'attachment' => $this->attachment,
        ]
      ];
   */
  public function getMailObject();


  /**
   * @return bool
   */
  public function send();


  /**
   * @param array   $to
   * @param string  $subject
   * @param string  $body
   * @return $this
   */
  public function mail(array $to, string $subject = null, string $body = null);


  /**
   * @param string  $name
   * @return $this
   */
  public function senderName(string $name);


  /**
   * @param string  $email
   * @param string  $name [optional]
   * @return $this
   */
  public function to(string $email, string $name = null);


  /**
   * @param string  $subject
   * @return $this
   */
  public function subject(string $subject);


  /**
   * @param string  $html_content
   * @return $this
   */
  public function html(string $html_content);


  /**
   * @param string  $file_path
   * @return $this
   */
  public function htmlFile(string $file_path);


  /**
   * @param string  $text_content
   * @return $this
   */
  public function text(string $text_content);


  /**
   * @param string  $file_path
   * @return $this
   */
  public function textFile(string $file_path);


  /**
   * @param string  $file_path
   * @return $this
   */
  public function attachment(string $file_path);


  /**
   * @return string
   */
  public function getError();


}

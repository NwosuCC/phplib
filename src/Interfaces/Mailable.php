<?php

namespace Orcses\PhpLib\Interfaces;


interface Mailable
{

  /**
   * @param array   $to
   * @param string  $subject
   * @param string  $body
   * @return $this
   */
  public function mail(array $to, string $subject = null, string $body = null);


  /**
   * @return bool
   */
  public function send();


  /**
   * @param string  $name
   * @return $this
   */
  public function senderName(string $name = null);


  /**
   * @param string  $email
   * @return $this
   */
  public function to(string $email);


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
  public function error();


}

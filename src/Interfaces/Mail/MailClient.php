<?php

namespace Orcses\PhpLib\Interfaces\Mail;


interface MailClient
{

  /**
   * @param \Orcses\PhpLib\Interfaces\Mail\Mailable $mail
   * @return bool
   */
  public function send(Mailable $mail);


  /**
   * @return string
   */
  public function getError();


}

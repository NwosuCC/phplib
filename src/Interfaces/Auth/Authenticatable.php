<?php

namespace Orcses\PhpLib\Interfaces\Auth;


interface Authenticatable
{
  /**
   * Attempts to create new session using supplied credentials
   * From the credentials, compiles and returns the query $where and the $password
   *
   * @param array $vars The credentials - ['email' | 'username', 'password']
   * @return array [$where, $password]
   */
  public function retrieveByCredentials(array $vars);


  /**
   * Attempts to authenticate using supplied token
   * Compiles and returns the query $where
   *
   * @param string $token The token to verify
   * @return array $where
   */
  public function retrieveByToken(string $token);


  /**
   * Validates an existing session
   *
   * @return bool
   */
  public function validateSession();

  
}
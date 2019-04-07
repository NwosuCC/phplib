<?php

namespace Orcses\PhpLib\Interfaces;


interface ProviderInterface
{
  /**
   * Registers services before application runs
   */
  public function register();


  /**
   *
   */
  public function boot();

  
}
<?php

namespace Orcses\PhpLib\Middleware\Auth;


use Closure;
use Orcses\PhpLib\Request;
use Orcses\PhpLib\Middleware\Middleware;


class Api extends Middleware
{

  public function handle(Request $request, Closure $next){
    // Auth

    return  $next( $request );
  }

}
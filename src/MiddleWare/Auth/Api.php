<?php

namespace Orcses\PhpLib\Middleware\Auth;


use Closure;
use Orcses\PhpLib\Access\Auth;
use Orcses\PhpLib\Request;
use Orcses\PhpLib\Middleware\Middleware;


class Api extends Middleware
{

  public function handle(Request $request, Closure $next)
  {
    $auth_bearer = $request->getHeader('Authorization');

    $token = trim( str_replace('Bearer', '', $auth_bearer));
    pr(['usr' => __FUNCTION__, '$token' => $token, '$auth_bearer' => $auth_bearer, '$request' => $request->input()]);

    Auth::verify( $token );

    return  $next( $request );
  }


}
<?php

namespace Orcses\PhpLib\Middleware\Auth;


use Closure;
use Orcses\PhpLib\Request;
use Orcses\PhpLib\Access\Auth;
use Orcses\PhpLib\Middleware\Middleware;


class Api extends Middleware
{

  public function handle(Request $request, Closure $next)
  {
    $auth_bearer = $request->getHeader('Authorization');

    $token = trim( str_replace('Bearer', '', $auth_bearer));

    if( ! Auth::verify( $token )){

      Request::abort(
        auth()::error( auth()::INVALID_TOKEN ), true
      );

      exit(1);
    }

    return $next( $request );
  }


}
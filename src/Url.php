<?php

namespace Orcses\PhpLib;


class Url {
  private static $common_TLDs = [
    'com', 'org', 'app', 'co', 'biz', 'io'
  ];

  private static function getRegexpPattern(){
    // Complete URL Regexp Pattern:
    $regexp  = "((https?|ftp)://)?"; // SCHEME: 1-2
    $regexp .= "(([a-z0-9+!*(),;?&=_$.-]+)(:([a-z0-9+!*(),;?&=_$.-]+))?@)?"; // User and Pass: 3-6
    $regexp .= "((([a-z0-9-]*\.)*(((com|org|app|co|biz)\.)?[a-z]{2,4})|localhost)"; // Host:
    $regexp .= "|"; // OR
    $regexp .= "[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})"; // IP:
    $regexp .= "(:([0-9]{2,5}))?"; // Port
    $regexp .= "((/([a-z0-9+_$%-]+[=.]?[a-z0-9+_$%-]+)+)*/?)?"; // Path: 14-15
    $regexp .= "(\?([a-z+&\_$.-][a-z0-9;:@&%=+/_$.-]*))?"; // GET Query
    $regexp .= "(#/?([a-z_.-][a-z0-9+_$%-.=/]*))?"; // Anchor

    return $regexp;
  }

  private static function clean($url){
    return preg_replace( '/[\x{200B}-\x{200D}\x{FEFF} ]/u', '', $url );
  }

  private static function getTLD($matches, $index){
    return (in_array($matches[$index], static::$common_TLDs))
      ? $matches[$index] .'.'. $matches[$index + 1]
      : $matches[$index + 1];
  }

  static function parseUrl($url){
    $regexp = static::getRegexpPattern();
    $url = static::clean($url);

    if(preg_match("~^$regexp$~i", $url, $matches)){
      $matches = array_pad($matches, 22, '');

      $parsed_url = [
        'scheme' => $matches[2],
        'user' => $matches[4],
        'pass' => $matches[6],
        'host' => $matches[7],
        'tld' => static::getTLD($matches, 9),
        'port' => $matches[14],
        'path' => $matches[15],
        'query' => $matches[19],
        'hash' => $matches[21]
      ];
    }
    return (!empty($parsed_url)) ? $parsed_url : null;
  }

}
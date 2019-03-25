<?php

namespace Orcses\PhpLib\Access;


class Session {

    public function __construct(){
        /*if(!session_id()){
          session_start();
        }*/
    }

    public static function set($key, $value){
        if(!empty($key)){
          $_SESSION[$key] = $value;
        }
        return static::get($key);
    }

    public static function get($keys = ''){
        $single_key = !is_array($keys);
        $session = [];

        if($single_key){ $keys = [$keys]; }

        foreach($keys as $key){
            $session[] = !empty($_SESSION[$key]) ? $_SESSION[$key] : null;
        }

        return ($single_key) ? $session[0] : $session;
    }

    public static function remove($key){
        if(isset($_SESSION[$key])){
          unset($_SESSION[$key]);
        }
    }

    public static function destroy(){
        /*try {
          session_destroy();
        }
        catch (Exception $e){
          // return $e->getMessage();
        }*/
    }

}


<?php

namespace Orcses\PhpLib;


Abstract class Result {
    private static $reports;

    public static function prepare($result){
        global $_REPORTS;
        static::$reports = $_REPORTS;
        $notice = [];

        if(is_array($result) and count($result) >= 2){
            if(!isset($result[2])){ $result[2] = []; }
        }else{
            return null;
        }

        list($function, $error_number, $info) = $result;

        if(is_array($error_number)){
            list($error_number, $replaces) = $error_number;
        }

        if(strlen($error_number) > 1){
            $indices = str_split($error_number);
            list($error_number, $message_index) = array_splice($indices, 0, 2);
//            array_splice($indices, 0, 2);
        }

        if(!$error_number){
            $result = static::$reports[$function]['success'];
            if(!empty($message_index)){ $result[1] = $result[1][$message_index]; }
        }else{
            $result = static::$reports[$function]['error'][$error_number];
        }

        if(isset($indices)){
            $notice = static::$reports[$function]['error'];
            $index_results = [];
            foreach($indices as $index){ $index_results[] = $notice[$index]; }
            $notice = $index_results;
        }

        if(!empty($replaces) and is_array($replaces)){
            foreach ($replaces as $find => $replace){
                $result[1] = str_replace('{'.$find.'}', $replace, $result[1]);
            }
        }
        if(!empty($notice)){
            $result[1] = [$result[1], $notice];
        }

        return [$result, $info];
    }

    /*public static function get_components($result){
        $indices = chunk_split($result[0], 2);
        list($report_code, $error_number) = $indices;
        foreach (static::$reports as $report_index => $report){
            if($report_code == $report['code']){
                return [$report_index, $error_number];
            }
        }
        return null;
    }*/

    public static function dispatch($result, $info){
        list($code, $message) = $result;
        $response = [
            'status' => static::status($code),  'code' => $code,
            'message' => $message,              'info' => $info
        ];
        static::send_response($response);
    }

    private static function status($code){
        return (static::successful($code)) ? 'Success' : 'Error';
    }

    public static function successful($result){
        $code = (is_array($result)) ? $result[0] : $result;
        $success_codes = ['0', '00', '000'];
        return (in_array($code, $success_codes));
    }

    private static function send_response($response){
      /* NOTE:
       * Webpack devServer proxy now takes care of the CORS error even without the (otherwise required) headers below
       */
//      header('Access-Control-Allow-Origin: http://localhost:8080');
//      header('Access-Control-Allow-Headers: Content-Type');

      die(json_encode($response));
    }

}
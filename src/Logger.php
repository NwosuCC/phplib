<?php

namespace Orcses\PhpLib;


class Logger {
    private static $files = [
        'whits' => '/../hts-log.txt',
        'tasks' => '/../misc/dlv-log.txt',
        'error' => '/../misc/err-log.txt',
    ];

    public static function log($type, $details){
        if(empty(static::$files[$type]) or empty($details)){
            return false;
        }

        if($report = static::composeReport($type, $details)){
            $logfile = __DIR__ . static::$files[$type];
            $fileHandle = fopen($logfile,'a');
            fwrite($fileHandle, $report);
            fclose($fileHandle);
        }
        return !empty($report);
    }

    private static function composeReport($type, $details){
        $server_host = $_ENV['server_host'];
        $report = null;

        if($type == 'tasks'){
            list($file, $delivery, $email, $name) = $details;

            if(file_exists($file) and in_array($delivery, [1,2])){
                $size = static::formatFileSize(filesize($file));
                $file = explode('/', $file);
                $file = end($file);

                $actions = ['1' => 'sent to email', '2' => 'downloaded by' ];
                $delivered_by = $actions[$delivery];
                $report = "File {file} {$delivered_by} {mail} [{name}] from ".$server_host;

                $report = str_replace('{file}', $file . ' ['.$size.']',
                    str_replace('{mail}', $email,
                        str_replace('{name}', $name, $report)));
                $report .= " at " . date('d-m-Y h:i:s a',time()) . "\n";
            }

        }else if($type == 'error'){
            list($code, $error) = $details;
            $report  = "Error [{$code}]: {$error}"
                     . " Time: " . date('d-m-Y h:i:s a', time()) . PHP_EOL;
        }

        return $report;
    }

    private static function formatFileSize($size){
        if($size > pow(1024,2)){
            $size = ($size / pow(1024,2));  $rate = " MB";
        }elseif($size > 1024){
            $size = ($size / 1024);  $rate = " kB";
        }else{
            $rate = " Bytes";
        }
        return number_format($size,2,'.',',') . $rate;
    }

}
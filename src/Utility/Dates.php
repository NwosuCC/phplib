<?php

namespace Orcses\PhpLib\Utility;


class Dates {

    public static function setTimezone($timezone)
    {
        date_default_timezone_set($timezone);
    }


    public static function dateTime_to_UnixTimestamp($date)
    {
        $hour = $minute = $second = $month = $day = $year = 0;

        if(!empty($date)){
            extract( date_parse($date) );

            $date_stamp = mktime($hour,$minute,$second,$month,$day,$year);
        }

        return (!empty($date_stamp)) ? $date_stamp : NULL;
    }


    public static function now()
    {
      return static::formatDateTime( time() );
    }


    public static function formatDateTime($timestamp, $type = '', $delimiter = '')
    {
        switch($type){
            case 'd':  	{ $format = ($delimiter) ? str_replace('-',$delimiter,"Y-m-d")   : "Y-m-d";	} break;
            case 'dr': 	{ $format = ($delimiter) ? str_replace('-',$delimiter,"d-m-Y")   : "d-m-Y";	} break;
            case 'T':  	{ $format = ($delimiter) ? str_replace(':',$delimiter,"H:i:s")   : "H:i:s";	} break;
            case 't':  	{ $format = ($delimiter) ? str_replace(':',$delimiter,"h:i:s A") : "h:i:s A";	} break;
            case 'df': 	{ $format = "D, M j, Y";														  	        } break;
            case 'dtf': { $format = "D, M j, Y h:i:s A";													        } break;
            case 'dT':  { $format = ($delimiter) ? str_replace('-',$delimiter,"Y-m-d h:i:s A") : "Y-m-d h:i:s A";	} break;
            default:    { $format = ($delimiter) ? str_replace(':',$delimiter,"Y-m-d H:i:s")   : "Y-m-d H:i:s";	} break;
        }
        return (is_numeric($timestamp)) ? date($format,$timestamp) : NULL;
    }

}
<?php

namespace Orcses\PhpLib\Utility;


class HtmlTable
{

  public static function html_table_headers($html_table_headers, $db_table_fields){
    if(!is_array($html_table_headers)){ $html_table_headers = []; }
    if(!is_array($db_table_fields)){ $db_table_fields = []; }

    foreach($db_table_fields as $i => $column){
      if($exp = explode('as', $column)){
        $column = trim((isset($exp[1])) ? $exp[1] : $exp[0]);
      }
      if($exp = explode('.', $column)){
        $column = trim((isset($exp[1])) ? $exp[1] : $exp[0]);
      }
      if(isset($html_table_headers[$i])){
        $html_table_headers[$column] = $html_table_headers[$i];
        unset($html_table_headers[$i]);
      }
    }
    return $html_table_headers;
  }


}


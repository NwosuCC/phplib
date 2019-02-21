<?php
function pr($data, $json = 1, $exit = 1, $html = 0){
    $newlines = ($html === 1) ? '<br><br>' : "\n\n";
    if($json !== 0){ echo json_encode($data); }
    elseif(is_array($data)){ print_r($data); }
    else{ var_dump($data); }
    echo $newlines;
    if($exit !== 0){ exit; }
}


class Utility
{
    // Off: If true, removes columns specified in $parts and returns the rest
    //      Else, returns only the columns specified in $parts
    public static function array_slice_parts($parts, $array, $off = false){
        if(!is_array($parts) or !is_array($array)){ return null; }

        if($off){
            $parts = array_diff(array_keys($array), $parts);
        }
        // Consider when $array has keys 'parts', 'array' etc
        extract($array);

        foreach($parts as $key => $value){
            if(is_array($value)){
                $parts[$key] = $value[1];
                ${"$value[1]"} = ${"$value[0]"};
            }
        }
        return compact($parts);
    }

    public static function clean($string){
        return trim(htmlspecialchars(stripslashes($string)));
    }

    public static function hash($vars, $start = '', $length = ''){
        $start = (is_numeric($start)) ? intval($start) : 13;
        $length = (is_numeric($length)) ? intval($length) : 9;
        return substr(sha1($vars.microtime()), $start, $length);
    }

    public static function make_password($vars){
        $salt_1  = substr(sha1($vars), 1, 22);
        $crypt_1 =  crypt($vars, '$2a$09$'.$salt_1.'$');
        $salt_2  = substr(sha1($vars), 18, 22);
        $crypt_2 =  crypt($vars, '$2a$09$'.$salt_2.'$');

        $double_crypt = substr($crypt_1, -15) . substr($crypt_2, -17);
        $crypt_BlowFish_salt = substr($double_crypt, 7, 22);

        return crypt($vars, '$2a$09$'.$crypt_BlowFish_salt.'$');
    }

    public static function make_id($type, $vars, $start = '', $length = '', $table_col = []){
        $attempts = 1;   $max_attempts = 5;
        while(empty($id) and $attempts < $max_attempts){
            switch ($type){
                case 'id' : {
                    if($table_col){
                        list($table, $column) = $table_col;
                        $exists = true;
                        while($exists and $attempts++ < $max_attempts){
                            $id = static::hash($type.$vars, $start, $length);
                            $where = "WHERE $column = '$id'";
                            $exists = Queries::select($table, $column, $where)->first();
                        }
                    } // Else, use hash() function above
                } break;

                case 'pw' : {
                    $id =  static::make_password($type.$vars);
                } break;
            }
        }
        return (isset($id)) ? $id : null;
    }

    public static function exists_id($table, $column_name, $id, $status = ''){
        list($column_name, $id) = Queries::escape([$column_name, $id]);
        $where = "WHERE $column_name = '$id'";
        if($status !== ''){
            $where .= ($status !== '0') ? " AND status = 1" : " AND status BETWEEN 1 AND 2";
        }
        return Queries::select($table, '', $where)->to_array();
    }

    public static function strip_guarded_columns($table, $row){
        $x_columns = Schema::tables($table, 'guarded');
        return static::array_slice_parts($x_columns, $row, true);
    }

    public static function pluck_columns($rows, $columns){
        if(!is_array($rows)){ $rows = []; }
        $plucked_rows = [];
        foreach($rows as $r => $row){
            if($columns and is_array($columns)){
                foreach($columns as $c => $column){ $plucked_rows[$column][] = $row[$column]; }
            }
        }
        return $plucked_rows;
    }

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

    /**
     * @author      Aidan Lister <aidan@php.net>
     * @version     1.0.1
     * @link        http://aidanlister.com/2004/04/recursively-copying-directories-in-php
     * @param       string   $source        Source path
     * @param       string   $destination   Destination path
     * @return      bool     Returns TRUE on success, FALSE on failure
     */
    public static function copy_recursive($source, $destination, $permissions = []){
        // Check for symlinks
        if (is_link($source)) { return symlink(readlink($source), $destination); }

        // Simple copy for a file
        if (is_file($source)) { return copy($source, $destination); }

        // Make destination directory
        if (!is_dir($destination)) { 
            mkdir($destination);
            if(!empty($permissions['group'])){ chgrp($destination, $permissions['group']); }
            if(!empty($permissions['mode'])){ chmod($destination, $permissions['mode']); }
        }

        // Loop through the folder
        $dir = dir($source);
        while (false !== ($entry = $dir->read())) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') { continue; }

            // Deep copy directories
            static::copy_recursive("$source/$entry", "$destination/$entry");
        }

        // Clean up
        $dir->close();
        return true;
    }

}


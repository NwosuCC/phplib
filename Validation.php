<?php

class Validation {
    private static $failed_rule;

    // [Function name, [Argument variables names]]
    private static $aliases = [
        'anu' => ['alphanumeric'],
        'ans' => ['alphanumeric_space'],
        'anc' => ['alphanumeric_chars'],
        'adr' => ['address'],
        'xtr' => ['extraFields'],
        'eml' => ['email'],
        'pwd' => ['password', ['no_validate']],
        'chk' => ['checkbox', ['required', 'defaultValue']],
        'txt' => ['text'],
        'url' => ['url'],
        'fil' => ['filePath'],
        'len' => ['stringLength', ['min', 'max']],
        'nuf' => ['numberFormat', ['way', 'toFixed']],
        'cnf' => ['currencyNumberFormat', ['way']],
        'bnf' => ['bankNumberFormat', ['type']],
        'pnf' => ['phoneNumberFormat'],
        'dts' => ['datePickerDate_toTimestamp'],
        'fcn' => ['firstCharacterNotNumber'],
    ];

    private static $check = [
        'r' => ['required', '{var} is required'],
    ];

    public static function clean($string) {
		return stripslashes(htmlentities(trim($string)));
    }

    private static function get_arguments($field_parameters, $count){
        if(isset($field_parameters[2]) and !is_array($field_parameters[2])){
            $arguments = null;
        }else{
            $arguments = (isset($field_parameters[2])) ? $field_parameters[2] : [];
            for($index = 0; $index < $count; $index++){
                if(!isset($arguments[$index])){ $arguments[$index] = null; }
            }
        }
        return $arguments;
    }

    private static function check_rules($result, $rules){
        foreach($rules as $rule){
            $function = static::$check[$rule][0];
            if(!static::$function($result)){ static::$failed_rule = $rule; break; }
        }
        return (!$rules or !static::$failed_rule);
    }

    public static function run($post, $allFields){
        $vars = $errors = $checked_fields = [];
        foreach($post as $key => $value){
            if(array_key_exists($key,$allFields)){
                list($rules, $field_name) = $allFields[$key];
                $rules = explode('|', $rules);
                $alias = array_shift($rules);

                if(array_key_exists($alias, static::$aliases)){
                    $checked_fields[] = $key;

                    $alias_contents = static::$aliases[$alias];
                    $function = $alias_contents[0];
                    $arguments_count = (count($alias_contents) > 1) ? count($alias_contents[1]) : 0;
                    $arguments = static::get_arguments($allFields[$key], $arguments_count);

                    if($alias !== 'pwd'){ $value = static::clean($value); }

                    list($validated, $result) = static::$function($value, $arguments);
                    $value_okay = ($validated and static::check_rules($result, $rules));

                    if($value_okay){
                        $vars[$key] = $result;
                    }else{
                        //$aliases_keys = array_keys(static::$aliases);
                        if(!$validated){
                            //$error_number = 10 + array_search($alias, $aliases_keys) + 1;
                            $report = str_replace('{var}', $field_name, $result);
                        }else{
                            //$last_aliases_index = count($aliases_keys) - 1;
                            //$check_keys = array_keys(static::$check);
                            //$error_number = 10 + array_search(static::$failed_rule, $check_keys) + $last_aliases_index;
                            $report = static::$check[static::$failed_rule][1];
                            $report = str_replace('{var}', $field_name, $report);
                        }
                        $errors[] = ['field' => $key, 'text' => $report];
                    }
                }
            }
        }

        if($omittedFields = array_diff(array_keys($allFields), $checked_fields)){
            foreach ($omittedFields as $field){
                $report = static::$check['r'][1];
                $report = str_replace('{var}', $allFields[$field][1], $report);
                $errors[] = ['field' => $field, 'text' => $report];
            }
        }

        return [$vars, $errors];
    }

    public static function required($value){
        return (trim($value) !== '');
    }

    private static function get_charsLiterals($chars){
        if(!is_array($chars)){ return false; }else{ $chars = array_unique($chars); }

        $characters = [
            'dot' => '.', 'comma' => ',', 'hyphen' => '-', 'underscore' => '_', 'colon' => ':',
            'semi-colon' => ';', 'plus' => '+', 'equals' => '=', 'exclamation' => '!', 'spaces' => ' ',
            'at' => '@', 'hash' => '#', 'dollar' => '$', 'percent' => '%', 'ampersand' => '&', 'pipe' => '|',
            'asterisk' => '*', 'less_than' => '<', 'greater_than' => '>', 'question_mark' => '?',
            'forward_slash' => '/', 'back_slash' => '\\', 'single_quote' => '\'', 'double_quote' => '"',
        ];
        $paired_characters = [
            'parentheses' => ['(',')'], 'brackets' => ['[',']'], 'braces' => ['{','}'],
        ];
        $doubles = [];
        foreach ($paired_characters as $pair){
            $doubles = array_merge($doubles, $pair);
        }

        $chars_names = [];   $n = 0;
        $chars_count = count($chars);
        foreach ($chars as $i => $char){
            if(!$name = array_search($char, $characters)){
                if(in_array($char, $doubles)){
                    foreach ($paired_characters as $key => $values){
                        if(in_array($char, $values)){ $name = $key;  break; }
                    }
                }
            }

            if($name){
                $chars_names[$n] = $name;
                if(($chars_count > 1) and ($i === ($chars_count - 1))){
                    $chars_names[$n] = 'or '.$chars_names[$n];
                }
                $n++;
            }
        }
        if(!empty($chars_names)){ $chars_names = implode(', ', $chars_names); }
        return $chars_names;
    }

    private static function escape_chars($chars){
        $escaped_chars = [
            '\\', '/', '.', '+', '-', '*', '?', '[', ']', '(', ')', '{', '}', ':'
        ];

        foreach ($chars as $index => $char){
            if($key = array_search($char, $escaped_chars)){
                $chars[$index] = "\\".$escaped_chars[$key];
            }
        }
        return $chars;
    }

    public static function alphanumeric_chars($string, $chars = []){
        // Unicode for all characters support
        $esc_chars = static::escape_chars($chars);
        if(is_array($esc_chars)){ $esc_chars  = implode(',', $esc_chars); }
        // if($esc_chars){ pr($esc_chars); }

        // Try and return, instead, the chars that are NOT allowed but found in the $string
        /*$match = preg_match("/^[\p{L}\p{N}$esc_chars]+$/", $string, $matches);
        if(!$match){ pr([$string, $esc_chars, $matches]); }*/

        $valid = ($string == '' or preg_match("/^[\p{L}\p{N}$esc_chars]+$/",$string));
        $string = trim($string);
        $errorMsg = '';
        if(!$valid){
            $chars_names = static::get_charsLiterals($chars);
            $errorMsg = "{var} may contain only letters and numbers"
                . (($chars) ? ", plus (optional) $chars_names" : '');
        }
        return ($valid) ? [true,$string] : [null,$errorMsg];
    }

    public static function alphanumeric($string){
        return static::alphanumeric_chars($string);
    }

    public static function alphanumeric_space($string){
        $allowedChars = [' '];
        return static::alphanumeric_chars($string, $allowedChars);
    }

    public static function address($string){
        $allowedChars = ['#', '.', ','];
        return static::alphanumeric_chars(trim($string), $allowedChars);
    }

    public static function extraFields($string){
        $allowedChars = ['_', ' ', '-', '\/'];
        return static::alphanumeric_chars(trim($string), $allowedChars);
    }

    public static function email($string){
        $allowedChars = ['_', '@', '.'];
        list($valid,$returnValue) = static::alphanumeric_chars($string, $allowedChars);
        $errorMsg = '';
        if(!$valid){ $errorMsg = "{var} contains invalid characters."; }
        else{
            if(!filter_var($returnValue, FILTER_VALIDATE_EMAIL) === false){}
            else{ $valid = false;  $errorMsg = "Valid {var} is required."; }
        }
        return ($valid) ? [true,$returnValue] : [null,$errorMsg];
    }

    public static function text($string){
        /*$allowedChars = ['#', '.', ',', '_', ' ', '-', '@', '\/'];
        list($valid, $returnValue) = static::alphanumeric_chars($string, $allowedChars);
        $errorMsg = (!$valid) ? $returnValue : '';
        return ($valid) ? [true,$returnValue] : [null,$errorMsg];*/
        return [true, $string];
    }

    public static function url($string){
        $validUrl = $errorMsg = '';
        if(!filter_var($string, FILTER_VALIDATE_URL) === false){
            $valid = true; 	 $validUrl = $string;
        }else{
            $valid = false;  $errorMsg = "Valid {var} is required.";
        }
        return ($valid) ? [true,$validUrl] : [null,$errorMsg];
    }

    public static function filePath($string){
        //$str = implode('',explode('.',implode('',explode('/',$string))));
        $validUrl = $errorMsg = '';
        $stripped = preg_replace("/[\.\/]+/", '', $string);

        if(ctype_alnum($stripped)){
            $valid = true; 	 $validUrl = $string;
        }else{
            $valid = false; 	$errorMsg = "{var} file path is invalid.";
        }
        return ($valid) ? [true,$validUrl] : [null,$errorMsg];
    }

    public static function stringLength($string, $arguments = []){
        list($min, $max) = $arguments;
        if(!$min and !$max){
            die("Function 'string_length(string,min,max)': 2nd and/or 3rd Parameter(s) required.");
        }

        $string = trim($string);
        if($min and $max){
            $in = strlen($string) >= $min;   $ax = strlen($string) <= $max;
            $valid = ($in and $ax);          $min_checked = $max_checked = true;
        }elseif($min){
            $valid = $in = (strlen($string) >= $min);   $min_checked = true;
        }elseif($max){
            $valid = $ax = (strlen($string) <= $max);   $max_checked = true;
        }else{
            $valid = false;
        }

        $errorMsg = '';
        if(!($string !== '' and $valid)){
            if(!empty($min_checked) and empty($in)){
                $errorMsg = '{var} must not be less than '.$min.' characters';   $join = true;
            }
            if(!empty($max_checked) and empty($ax)){
                $errorMsg .= (!empty($join) ? ' or ': '{var} must not be ') . ' more than '.$max.' characters'; }
        }
        return ($string !== '' and $valid) ? [true,$string] : [null,$errorMsg];
    }

    public static function numberFormat($number, $arguments = []){
        if(count($arguments) ==1){ $arguments[1] = null; }
        list($way, $toFixed) = $arguments;
        if(empty($way)){
            die("Function 'numberFormat(String $number, Int $way, Bool $toFixed)': 2nd Parameter required.");
        }
        //$number = str_replace(',','',str_replace(' ','',trim($number)));
        $number = preg_replace('/[, ]+/','', trim($number));
        if(is_numeric($number)){
            if($toFixed){ $number = round($number,$toFixed); } // round($number,$toFixed,PHP_ROUND_HALF_DOWN)
            if($way == 1){ $validNumber = number_format($number); }
            elseif($way == 2){ $validNumber = $number; }
        }elseif($number === ''){
            $validNumber = $number;
        }
        $errorMsg = '{var} format is invalid.';
        return (isset($validNumber)) ? [true,$validNumber] : [null,$errorMsg];
    }

    /*  Method 'currencyNumberFormat()': Formats a currency number in human readable form, OR strips its formatting.
     *  $way: true  - format as [nn,nnn,nnn.nn] Number.
              false - strip formatting and return numeric [nnnnnnnn.nn]
     */

    public static function currencyNumberFormat($number, $arguments = []){
        list($way) = $arguments;
        if(empty($way)){
            die("Function 'currencyFormat(String $number, Int $way)': 2nd Parameter required.");
        }
        list($valid, $returnValue) = static::numberFormat($number,$arguments);
        $errorMsg = '';

        if($valid){
            $number_array = explode('.', $returnValue);
            $parts_count = count($number_array);
            $decimal = ($parts_count == 2) ? end($number_array) : '';

            if($parts_count == 1 or strlen($decimal) <= 2 or $returnValue === ''){
                $validNumber = $returnValue;
            }else{
                $errorMsg = "{var} figure must have only two (2) decimal places, if necessary.";
            }
        }else{
            $errorMsg = $returnValue;
        }
        return (isset($validNumber)) ? [true,$validNumber] : [null,$errorMsg];
    }

    // $type: bvn, acct, etc
    /*public static function bankNumberFormat($number, $arguments = []){
        list($type) = $arguments;*/
    public static function bankNumberFormat($number){
        list($valid, $returnValue) = static::numberFormat($number,[2]);
        $errorMsg = '';

        if($valid){
            if(strlen($returnValue) === 10){
                $validNumber = $returnValue;
            }else{
                $errorMsg = "Valid {var} is required.";
            }
        }else{ $errorMsg = $returnValue[1];	}
        return (isset($validNumber)) ? [true,$validNumber] : [null,$errorMsg];
    }

    public static function phoneNumberFormat($phoneNumber){
        //$phone = str_replace('+','',str_replace('-','',str_replace(' ','',$phoneNumber)));
        $phone = preg_replace('/[\+\- ]+/','',$phoneNumber);
        if(!empty($phone)){
            $nig = array('070','071','080','081','090','091');
            if(in_array(substr($phone,0,3),$nig)){
                $phone = substr_replace($phone,'234',0,1);
            }
            if(substr($phone,0,3) == '234' and strlen($phone) != 13){ $phone = null; }
        }
        $errorMsg = 'Valid {var} is required.';
        return (ctype_digit($phone)) ? [true,$phone] : [null,$errorMsg];
    }

    public static function checkbox($value, $arguments = []){
        list($required, $defaultValue) = $arguments;
        if($required and $defaultValue == ''){
            die("Function 'checkBox(String $value, Bool $required, String $defaultValue)': 2nd Parameter requires the 3rd.");
        }

        $valid = ($required) ? ($value == $defaultValue) : ($value != '');
		$errorMsg = '{var}: Value is required.';
        return ($valid) ? [true,$value] : [null,$errorMsg];
    }

    public static function password($password, $arguments = []){
        /*  Do NOT validate if supplied Password is for login
         *  If the 'no_validate' check is not used, then validation (e.g Min 8 chars) will
         *  apply for login too, which is not ideal!
         */
        list($validation_type) = $arguments;
        if($validation_type === 0){ return [true, $password]; }

        $cfPassword = $errorMsg = '';
        if(is_array($password)){ list($password,$cfPassword) = $password; }

        $has_at_least_one_letter = (preg_match('/[A-z]/',$password));
        $has_at_least_one_number = (preg_match('/[0-9]/',$password));
        $has_compulsory_chars = ($has_at_least_one_letter and $has_at_least_one_number);
        if((strlen($password) < 8) or !$has_compulsory_chars){
            $errorMsg .= '{var} requires Minimum eight(8) characters with at least one alphabet and one digit.';
        }

        if($errorMsg == '' and $validation_type === 1 and $password !== $cfPassword){
            if($errorMsg != ''){ $errorMsg .= '<br/>'; }
            $errorMsg .= '{var} confirmation: Entries do not match.';
        }
        return (empty($errorMsg)) ? [true,$password] : [null,$errorMsg];
    }

    public static function datePickerDate_to_Timestamp($year_month_day){
        $errorMsg = '{var}: Date is invalid.';
        $date_stamp = null;

        if(is_array($year_month_day)){
        // If ($year_month_day == array($year,$month,$day))
            if(count($year_month_day) == 1 and !$year_month_day[0]){
                $date_stamp = 0;
            }else{
                // include Format for more accuracy
                list($year,$month,$day) = $year_month_day;
                if(is_numeric($year) && is_numeric($month) && is_numeric($day)){
                    $date_stamp = mktime(0,0,0,$month,$day,$year);
                }
            }
        }elseif(is_numeric($year_month_day)){
        // If ($year_month_day == timestamp)
            $month = $day = $year = $hour = $minute = $second = 0;
            extract(date_parse($year_month_day));
            $date_stamp = mktime(0,0,0, $month, $day, $year);
        }
        return ($date_stamp !== null) ? [true,$date_stamp] : [null,$errorMsg];
    }

    public static function firstCharacterNotNumber($string){
        $string = trim($string);
        $string_array = str_split($string);
        $valid = ($string == '' or !!ctype_digit($string_array[0]));
        $errorMsg = '{var} must not begin with a number.';
        return ($valid) ? [true,$string] : [null,$errorMsg];
    }

}


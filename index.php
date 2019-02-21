<?php
$php_libraries = [
  'Validation', 'Queries', 'Utility', 'Upload', 'Result'
];
foreach($php_libraries as $i => $script_name){
  require_once ("$script_name.php");
}

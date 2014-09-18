<?php
require_once('../config.php');
// variables to be set in config.php ?
$directory = '/home/www/tee14/temp/';
$delimiter = ";";
echo $directory;
echo  "\n";
    $iter = new DirectoryIterator($directory);  
    foreach($iter as $file ) {  
        if ( !$file->isDot() ) {  
            echo $file->getFilename()."\n";  
	    if(!file_exists($file->getFilename()) || !is_readable($file->getFilename()))
              echo "Error while reading " . $file->getFilename() . "\n";
            #else {
              $header = NULL;
              if (($handle = fopen($filename, 'r')) !== FALSE) {
               while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                 if(!$header)
                 $header = $row;
                 echo $row . "\n";
               }
             }  
            #}  
}
}
?>

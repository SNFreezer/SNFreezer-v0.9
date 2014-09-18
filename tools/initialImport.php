<?php
require_once('../config.php');
require_once('../function.php');
require_once('../twitteroauth.php'); 

// variables to be set in config.php ?
$directory = '/home/www/tee1/temp/';
date_default_timezone_set('UTC');
// add locale for UTF8 ?
$delimiter = ";";
$opDate = date("r");
$accounts = array();

$tconnection = new TwitterOAuth($tk_oauth_consumer_key, $tk_oauth_consumer_secret, $tk_oauth_token, $tk_oauth_token_secret);
$user_info = $tconnection->get('account/verify_credentials');
echo $user_info->screen_name . "\n";
echo $user_info->id . "\n";

    $iter = new DirectoryIterator($directory);  
    foreach($iter as $file ) {  
        if ( !$file->isDot() ) {  
          
            #echo $file->getFilename() . "\n";  
            #echo $file->getPath() . "\n";  
            $filename = $file->getPath() . "/" . $file->getFilename();
            echo "Importing: " . $filename . " - " . $opDate ."\n";
 
            $countryCode=$file->getExtension();
            #echo $countryCode . "\n";
 	    
           if(!file_exists($filename) || !is_readable($filename))
              echo "Error while reading " . $filename . "\n";
            else {
              $header = NULL;
              // fopen with ru option for reading utf8 files
              if (($handle = fopen($filename, 'ru')) !== FALSE) {
               while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                 $num = count($row);
	         #for ($c=0; $c < $num; $c++) {
                 #echo $row[0] . "\n";

                 $testq = "select * from QSoperation where lower(query_source) = lower('". $row[0] ."')" ;
                 $r = pg_query($pgdb->connection, $testq);
                 $numrows = pg_num_rows($r);

                 $pgrow = pg_fetch_row($r);
                 if ($numrows >0){
                   echo "SKIPED - " . $row[0] . " already exists in " . $pgrow[2] . " for country = " . $pgrow[1] . " \n";
                 }
                 else{       
                  $rowType="Keyword";
                  if (preg_match("/@/i", $row[0])){
                   $accounts[] = substr($row[0], 1);
                   $rowType = "Account";
                  }
                  else 
                   if (preg_match("/#/i", $row[0]))
                     $rowType = "Hashtag";
                
                  if (strcmp($rowType,"Account")!=0){  
                     $archiveResult = $tk->createArchive2(mysql_real_escape_string($row[0]),$rowType,NULL,'','',$user_info->screen_name,$user_info->id);
                     echo $archiveResult[0]. " for " . $rowType . " " . $row[0] . "\n";

                     $q = "insert into QSoperation(query_source, country_code, vm_source, operation, date) values(".
                                            "'".$row[0]."',".
                                            "'".$countryCode."',".
                                            "'".$youtwapperkeeper_useragent."',".
	                                    "'A',".
                                            "'".$opDate."')";                 
                     echo "INSERTED - " . $q . " \n";
                 
		     $r = pg_query($pgdb->connection, $q);
		 	if(!$r)
			{
				echo "PG ERROR ". $q ."\n";
			}
                  }
                 
                 } #end insert
               # }
               } # end of while
               echo "Processing ". count($accounts) . " account(s)\n";
               if(count($accounts) > 0)
               {
                while(count($accounts) > 100)
                {
                        $subarray_accounts = array_slice($accounts, 0, 100);

                        $search = $tconnection->get('users/lookup', array('screen_name' => implode(",", $subarray_accounts)));

                        for($i=0;$i<count($search);$i++)
                        {
                                $result = $tk->createArchive2('@'.$search[$i]->screen_name,'Account',$search[$i]->id,'','',$user_info->screen_name,$user_info->id);
                                $q = "insert into QSoperation(query_source, country_code, vm_source, operation, date) values(".
                                            "'@".$search[$i]->screen_name."',".
                                            "'".$countryCode."',".
                                            "'".$youtwapperkeeper_useragent."',".
                                            "'A',".
                                            "'".$opDate."')";
                                echo "INSERTED - " . $q . " \n";

                                $r = pg_query($pgdb->connection, $q);
                                if(!$r)
                                {
                                 echo "PG ERROR ". $q ."\n";
                                }

                                //echo $result[0]."\n";
                        }

                        $accounts = array_slice($accounts, 100, count($accounts));
                }

                $search = $tconnection->get('users/lookup', array('screen_name' => implode(",", $accounts)));

                for($i=0;$i<count($search);$i++)
                {
                        $result = $tk->createArchive2('@'.$search[$i]->screen_name,'Account',$search[$i]->id,'','',$user_info->screen_name,$user_info->id);
                        $q = "insert into QSoperation(query_source, country_code, vm_source, operation, date) values(".
                                            "'@".$search[$i]->screen_name."',".
                                            "'".$countryCode."',".
                                            "'".$youtwapperkeeper_useragent."',".
                                            "'A',".
                                            "'".$opDate."')";
                         echo "INSERTED - " . $q . " \n";
                                
                         $r = pg_query($pgdb->connection, $q);
                                if(!$r)
                                {
                                 echo "PG ERROR ". $q ."\n";
                                }

                        //echo $result[0]."\n";
                }
        }
 
               fclose($handle);
               unlink($filename);
             }  
            }  
}
}
?>


<?php
// load important files
require_once('config.php');
require_once('function.php');
require_once('twitteroauth_search.php');

// setup values
$pid = getmypid();
$script_key = uniqid();

//JN : introducing a sleep value to avoid high CPU load
//sleep value is initialized to 10 seconds
$sleep_value = 10;
//JN

$log = fopen("log/yourtwapperkeeper_stream_process_fail_pg.log", "w");

$q_test = "select 1";

echo "yourtwapperkeeper_stream_process\n\n";

// process loop
while (true)
{	
	echo "Beginning\n\n";
	
	$r_test_mysql = true;
	$r_test_pg = true;
	
	$r_test_mysql = mysql_query($q_test, $db->connection);
	
	//test pgdb connection only if PGSQL_INSERT == true
	if(PGSQL_INSERT == true)
	{
		$r_test_pg = pg_query($pgdb->connection, $q_test);
	}

	//if PGSQL_INSERT == false, $r_test_pg = true (jump to else)
	if(!$r_test_mysql || !$r_test_pg)
	{
		if(!$r_test_mysql && !$r_test_pg)
		{
			echo "ERROR : MYSQL AND POSTGRESQL DATABASES ARE DOWN :\n";
			echo "\tQuery : ".$q_test."\n";
			echo "\tMySQL error : ".mysql_error()."\n";
			echo "\tTime : ".time()."\n";
			echo "\n";
			
			$die_message = $snf_vm_name." (".time().", ".date_format(date_create(), 'Y-m-d H:i:s').") - ";
			$die_message = $die_message . "yourtwapperkeeper_stream_process.php has been killed (MySQL and PgSQL DBs are down)";
			mail("tee1@depinfo.u-bourgogne.fr", "YTK", $die_message);
			die($die_message);
		}
		else
		{
			if(!$r_test_mysql)
			{
				echo "ERROR : MYSQL DATABASE IS DOWN :\n";
				echo "\tQuery : ".$q_test."\n";
				echo "\tError : ".mysql_error()."\n";
				echo "\tTime : ".time()."\n";
				echo "\n";
				
				$die_message = $snf_vm_name." (".time().", ".date_format(date_create(), 'Y-m-d H:i:s').") - ";
				$die_message = $die_message . "yourtwapperkeeper_stream_process.php has been killed (MySQL DB is down)";
				mail("tee1@depinfo.u-bourgogne.fr", "YTK", $die_message);
				die($die_message);
			}
			
			if(!$r_test_pg)
			{
				echo "ERROR : POSTGRESQL DATABASE IS DOWN :\n";
				echo "\tQuery : ".$q_test."\n";
				echo "\tTime : ".time()."\n";
				echo "\n";
				
				$die_message = $snf_vm_name." (".time().", ".date_format(date_create(), 'Y-m-d H:i:s').") - ";
				$die_message = $die_message . "yourtwapperkeeper_stream_process.php has been killed (PG DB is down)";
				mail("tee1@depinfo.u-bourgogne.fr", "YTK", $die_message);
				die($die_message);
			}
		}
	}
	else
	{
		//JN : sleep to avoid high CPU load
		/*
		if($sleep_value < 0.1){
			echo $sleep_value." < 0.1, so ";
			$sleep_value = 1;
			echo "sleep value = ".$sleep_value."\n";
		}
		*/
		
		if($sleep_value > 60){
			echo $sleep_value." > 60, so ";
			$sleep_value = 60;
			echo "sleep value = ".$sleep_value."\n";
		}
		
		echo "Wait for : ".$sleep_value." seconds\n";
		sleep($sleep_value);
		echo "Let's go\n";
		
		//JN
		
		// lock up some tweets
		$q = "update rawstream set flag = '$script_key' where flag = '-1' limit $stream_process_stack_size";
		echo "update rawstream set flag = '$script_key' where flag = '-1' limit $stream_process_stack_size BEGINNING : ".microtime()."\n";
		$r= mysql_query($q, $db->connection);
		echo "update rawstream set flag = '$script_key' where flag = '-1' limit $stream_process_stack_size END : ".microtime()."\n";
		
		if(!$r)
		{
			echo "ERROR : UPDATE RAWSTREAM FAILED :\n";
			echo mysql_error()."\n";
			echo "\tQuery : ".$q."\n";
			echo "\tTime : ".time()."\n";
			echo "\n";
		}

		// get keyword into memory
		$q = "select id,keyword,account_id from archives";
		echo "select id,keyword,account_id from archives BEGINNING : ".microtime()."\n";
		$r = mysql_query($q, $db->connection);
		echo "select id,keyword,account_id from archives END : ".microtime()."\n";
		
		if(!$r)
		{
			echo "ERROR : SELECT FROM ARCHIVES FAILED :\n";
			echo mysql_error()."\n";
			echo "\tQuery : ".$q."\n";
			echo "\tTime : ".time()."\n";
			echo "\n";
		}
		
		$preds = array();
		
		//JN : get keyword and account_id
		echo "FETCH BEGINNING : ".time()."\n";
		while ($row = mysql_fetch_assoc($r)) {
			$preds[$row['id']] = array($row['keyword'], $row['account_id']);
		}
		echo "FETCH END : ".time()."\n";
		//JN
		
		// grab the locked up tweets and load into memory
		$q = "select * from rawstream where flag = '$script_key'";
		echo "select * from rawstream where flag = '$script_key' BEGINNING : ".microtime()."\n";
		$r = mysql_query($q, $db->connection);
		echo "select * from rawstream where flag = '$script_key' END : ".microtime()."\n";
		
		if(!$r)
		{
			echo "ERROR : SELECT FROM RAWSTREAM FAILED :\n";
			echo mysql_error()."\n";
			echo "\tQuery : ".$q."\n";
			echo "\tTime : ".time()."\n";
			echo "\n";
		}

		echo "Number of rows selected from rawstream : ".mysql_num_rows($r)." ";
		
		//JN : estimation of an optimal sleep_value
		//if less than $stream_process_stack_size rows have been processed, $sleep_value is too small
		if(mysql_num_rows($r) < $stream_process_stack_size)
		{
			echo "< ".$stream_process_stack_size."\n";
			
			if(mysql_num_rows($r) <> 0)
			{
				$sleep_value = ($stream_process_stack_size * $sleep_value) / mysql_num_rows($r);
				echo "New sleep value : ".$sleep_value."\n";
			}
			else
			{
				echo "Sleep value stays unchanged : ".$sleep_value."\n";
				//if 0 rows have been processed, $sleep_value stays unchanged
			}
		}
		
		//if $stream_process_stack_size rows have been processed, it possibly means that
		//rawstream has unprocessed rows left, so $sleep_value is too big
		else
		{
			echo "= ".$stream_process_stack_size."\n";
			
			//number of unprocessed rows (flag != $script_key)
			//~ $q2 = "select id from rawstream where flag != '$script_key'";
			//number of unprocessed rows (flag = -1)
			$q2 = "select count(id) from rawstream where flag = '-1'";
			$r2 = mysql_query($q2, $db->connection);
			
			$result_r2 = mysql_fetch_assoc($r2);
			$count_r2 = $result_r2['count(id)'];
			
			//~ echo "Remaining ".mysql_num_rows($r2)." rows in rawstream ";
			echo "Remaining ".$count_r2." rows in rawstream ";
			
			if($count_r2 < $stream_process_stack_size){
				echo "< ".$stream_process_stack_size."\n";
				//~ $sleep_value = ($stream_process_stack_size - mysql_num_rows($r2)) / (($stream_process_stack_size + mysql_num_rows($r2)) / $sleep_value);
				$sleep_value = 0.5;
				echo "New sleep value : ".$sleep_value."\n";
			}
			else{
				echo ">= ".$stream_process_stack_size."\n";
				//~ $sleep_value = 0.2;
				$sleep_value = 0.01;
				echo "New sleep value : ".$sleep_value."\n";
			}
		}



		//JN
		
		$batch = array();
		while ($row = mysql_fetch_assoc($r)) {
			$batch[] = $row;
		}
		
		echo "Beginning of tweets processing : ".microtime()."\n";
		
		// for each tweet in memory, compare against predicates and insert
		foreach ($batch as $tweet) {

			echo $tweet['id']."[".time()."](";
			
			foreach ($preds as $ztable=>$data)
			{
				$keyword = $data[0];
				$account_id = $data[1];
				
				//~ if (stristr(($tweet['text']." ".$tweet['initial_tweet_text']." ".$tweet['urls']." ".$tweet['medias_urls']), $keyword) == TRUE) {
				if (stripos(($tweet['text']." ".$tweet['initial_tweet_text']." ".$tweet['urls']." ".$tweet['medias_urls']), $keyword) !== FALSE) {
					
					echo "KW:".$keyword.":";
					
					$q_check = "select id from z_".$ztable." where id = '".$tweet['id']."'";
					$result_check = mysql_query($q_check, $db->connection);
					
					//check duplicate records
					if (mysql_num_rows($result_check)==0)
					{
						echo "INSERT,";
						
						//JN : add values for new attributes
						$q_insert = "insert into z_$ztable values (
							'stream','".
							mysql_real_escape_string($tweet['text'])."','".
							$tweet['to_user_id']."','".
							$tweet['from_user']."','".
							$tweet['id']."','".
							$tweet['from_user_id']."','".
							$tweet['iso_language_code']."','".
							mysql_real_escape_string($tweet['source'])."','".
							$tweet['profile_image_url']."','".
							$tweet['geo_type']."','".
							$tweet['geo_coordinates_0']."','".
							$tweet['geo_coordinates_1']."','".
							$tweet['created_at']."','".
							$tweet['time']."','".
							
							//new attributes
							mysql_real_escape_string($tweet['from_user_name'])."','".
							mysql_real_escape_string($tweet['from_user_location'])."','".
							mysql_real_escape_string($tweet['from_user_url'])."','".
							mysql_real_escape_string($tweet['from_user_description'])."','".
							$tweet['from_user_created_at']."','".
							$tweet['from_user_verified']."','".
							$tweet['from_user_contributors_enabled']."','".
							$tweet['truncated']."','".
							$tweet['in_reply_to_status_id']."','".
							mysql_real_escape_string($tweet['contributors'])."','".
							$tweet['initial_tweet_id']."','".
							mysql_real_escape_string($tweet['initial_tweet_text'])."','".
							mysql_real_escape_string($tweet['initial_tweet_user'])."','".
							$tweet['initial_tweet_time']."','".
							mysql_real_escape_string($tweet['user_mentions'])."','".
							mysql_real_escape_string($tweet['urls'])."','".
							mysql_real_escape_string($tweet['medias_urls'])."','".
							$tweet['hashtags']."','".
							$tweet['symbols'].
						"')";
						//JN

						$r_insert = mysql_query($q_insert, $db->connection);
						
						if (!$r_insert)
						{
							echo "\n\nERROR : MYSQL - INSERT :\n";
							echo mysql_error()."\n";
							echo "Query : ".$q_insert."\n";
							echo "\tTime : ".time()."\n";
							echo "\n\n";
						}
						else
						{
							mysql_query("update archives set count=count+1 where id = ".$ztable."", $db->connection);
						}
						
						if(PGSQL_INSERT == true)
						{
							$q_insert = "insert into gtweets values (
								'stream','".
								pg_escape_string($tweet['text'])."','".
								$tweet['to_user_id']."','".
								$tweet['from_user']."','".
								$tweet['id']."','".
								$tweet['from_user_id']."','".
								$tweet['iso_language_code']."','".
								pg_escape_string($tweet['source'])."','".
								$tweet['profile_image_url']."','".
								$tweet['geo_type']."','".
								$tweet['geo_coordinates_0']."','".
								$tweet['geo_coordinates_1']."','".
								$tweet['created_at']."','".
								$tweet['time']."','".
								
								//new attributes
								pg_escape_string($tweet['from_user_name'])."','".
								pg_escape_string($tweet['from_user_location'])."','".
								pg_escape_string($tweet['from_user_url'])."','".
								pg_escape_string($tweet['from_user_description'])."','".
								$tweet['from_user_created_at']."','".
								$tweet['from_user_verified']."','".
								$tweet['from_user_contributors_enabled']."','".
								$tweet['truncated']."','".
								$tweet['in_reply_to_status_id']."','".
								pg_escape_string($tweet['contributors'])."','".
								$tweet['initial_tweet_id']."','".
								pg_escape_string($tweet['initial_tweet_text'])."','".
								pg_escape_string($tweet['initial_tweet_user'])."','".
								$tweet['initial_tweet_time']."','".
								pg_escape_string($tweet['user_mentions'])."','".
								pg_escape_string($tweet['urls'])."','".
								pg_escape_string($tweet['medias_urls'])."','".
								$tweet['hashtags']."','".
								$tweet['symbols']."','".
								pg_escape_string($keyword)."','".
								$youtwapperkeeper_useragent.
							"')";
							//JN

							$r_insert = pg_query($pgdb->connection, $q_insert);
							
							if (!$r_insert)
							{
								echo "\n\nERROR : PGSQL - INSERT :\n";
								echo "Query : ".$q_insert."\n";
								echo "\tTime : ".time()."\n";
								echo "\n\n";
								fputs($log, $q_insert."\n\n");
							}
						}
					}
					else
					{
						echo "DUPLICATE,";
					}
					
				}
				
				//~ echo "\n".$tweet['from_user_id']." : ".gettype($tweet['from_user_id'])."\n";
				//~ echo "\n".$account_id." : ".gettype($account_id)."\n";
				
				//JN : if the tweet is from a followed user
				if ($tweet['from_user_id'] === $account_id) {

					echo "UID:".$account_id.":";
					
					$q_check = "select id from z_".$ztable." where id = '".$tweet['id']."'";
					$result_check = mysql_query($q_check, $db->connection);
					
					//check duplicate records
					if (mysql_num_rows($result_check)==0)
					{
						echo "INSERT,";
						
						$q_insert = "insert into z_$ztable values (
							'stream','".
							mysql_real_escape_string($tweet['text'])."','".
							$tweet['to_user_id']."','".
							$tweet['from_user']."','".
							$tweet['id']."','".
							$tweet['from_user_id']."','".
							$tweet['iso_language_code']."','".
							mysql_real_escape_string($tweet['source'])."','".
							$tweet['profile_image_url']."','".
							$tweet['geo_type']."','".
							$tweet['geo_coordinates_0']."','".
							$tweet['geo_coordinates_1']."','".
							$tweet['created_at']."','".
							$tweet['time']."','".
							
							//new attributes
							mysql_real_escape_string($tweet['from_user_name'])."','".
							mysql_real_escape_string($tweet['from_user_location'])."','".
							mysql_real_escape_string($tweet['from_user_url'])."','".
							mysql_real_escape_string($tweet['from_user_description'])."','".
							$tweet['from_user_created_at']."','".
							$tweet['from_user_verified']."','".
							$tweet['from_user_contributors_enabled']."','".
							$tweet['truncated']."','".
							$tweet['in_reply_to_status_id']."','".
							mysql_real_escape_string($tweet['contributors'])."','".
							$tweet['initial_tweet_id']."','".
							mysql_real_escape_string($tweet['initial_tweet_text'])."','".
							mysql_real_escape_string($tweet['initial_tweet_user'])."','".
							$tweet['initial_tweet_time']."','".
							mysql_real_escape_string($tweet['user_mentions'])."','".
							mysql_real_escape_string($tweet['urls'])."','".
							mysql_real_escape_string($tweet['medias_urls'])."','".
							$tweet['hashtags']."','".
							$tweet['symbols'].
						"')";
					
						$r_insert = mysql_query($q_insert, $db->connection);
						
						if (!$r_insert)
						{
							echo "\n\nERROR : MYSQL - INSERT :\n";
							echo mysql_error()."\n";
							echo "Query : ".$q_insert."\n";
							echo "\tTime : ".time()."\n";
							echo "\n\n";
						}
						else
						{
							mysql_query("update archives set count=count+1 where id = ".$ztable."", $db->connection);
						}
						
						if(PGSQL_INSERT == true)
						{
							$q_insert = "insert into gtweets values (
								'stream','".
								pg_escape_string($tweet['text'])."','".
								$tweet['to_user_id']."','".
								$tweet['from_user']."','".
								$tweet['id']."','".
								$tweet['from_user_id']."','".
								$tweet['iso_language_code']."','".
								pg_escape_string($tweet['source'])."','".
								$tweet['profile_image_url']."','".
								$tweet['geo_type']."','".
								$tweet['geo_coordinates_0']."','".
								$tweet['geo_coordinates_1']."','".
								$tweet['created_at']."','".
								$tweet['time']."','".
								
								//new attributes
								pg_escape_string($tweet['from_user_name'])."','".
								pg_escape_string($tweet['from_user_location'])."','".
								pg_escape_string($tweet['from_user_url'])."','".
								pg_escape_string($tweet['from_user_description'])."','".
								$tweet['from_user_created_at']."','".
								$tweet['from_user_verified']."','".
								$tweet['from_user_contributors_enabled']."','".
								$tweet['truncated']."','".
								$tweet['in_reply_to_status_id']."','".
								pg_escape_string($tweet['contributors'])."','".
								$tweet['initial_tweet_id']."','".
								pg_escape_string($tweet['initial_tweet_text'])."','".
								pg_escape_string($tweet['initial_tweet_user'])."','".
								$tweet['initial_tweet_time']."','".
								pg_escape_string($tweet['user_mentions'])."','".
								pg_escape_string($tweet['urls'])."','".
								pg_escape_string($tweet['medias_urls'])."','".
								$tweet['hashtags']."','".
								$tweet['symbols']."','".
								pg_escape_string($keyword)."','".
								$youtwapperkeeper_useragent.
							"')";
							//JN

							$r_insert = pg_query($pgdb->connection, $q_insert);
							
							if (!$r_insert)
							{
								echo "\n\nERROR : PGSQL - INSERT :\n";
								echo "Query : ".$q_insert."\n";
								echo "\tTime : ".time()."\n";
								echo "\n\n";
								fputs($log, $q_insert."\n\n");
							}
						}
					}
					else
					{
						echo "DUPLICATE,";
					}
				}
			}
			
			echo "),";
		}
		
		echo "\nEnd of tweets processing ".microtime()."\n\n";
		
		// delete tweets in flag
		$q = "delete from rawstream where flag = '$script_key'";
		echo "delete from rawstream where flag = '$script_key' BEGINNING : ".microtime()."\n";
		$r_delete = mysql_query($q, $db->connection);
		echo "delete from rawstream where flag = '$script_key' END : ".microtime()."\n";
		
		if (!$r_delete)
		{
			echo "\n\nERROR : MYSQL - DELETE FROM RAWSTREAM :\n";
			echo "Query : ".$q."\n";
			echo "\tTime : ".time()."\n";
			echo "\n\n";
		}
		
		echo "update counts BEGINNING : ".microtime()."\n";
		// update counts JN : too slow, update for each tweet is faster 
		/*
		foreach ($preds as $ztable=>$keyword) {
			$q_count = "select count(id) from z_$ztable";
			$r_count = mysql_query($q_count, $db->connection);
			$r_count = mysql_fetch_assoc($r_count);
			
			$q_update = "update archives set count = '".$r_count['count(id)']."' where id = '$ztable'";
			$r_update = mysql_query($q_update, $db->connection);
			
			if (!$r_update)
			{
				echo "\n\nERROR : MYSQL - UPDATE ARCHIVES :\n";
				echo "Query : ".$q."\n";
				echo "\tTime : ".time()."\n";
				echo "\n\n";
			}
		}
		*/
		echo "update counts END : ".microtime()."\n";
		
		// update pid and last_ping in process table
		mysql_query("update processes set last_ping = '".time()."' where pid = '$pid'", $db->connection);
		echo "update pid\n";
	}
	
	echo "End\n\n";
	
}

?>

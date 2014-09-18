<?php
/*
yourTwapperKeeper - Twitter Archiving Application - http://your.twapperkeeper.com
Copyright (c) 2010 John O'Brien III - http://www.linkedin.com/in/jobrieniii

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// Load important files
session_start();
require_once('config.php');
require_once('function.php');
require_once('twitteroauth.php');

ob_start();

// Ensure user is an administrator
if (!(in_array($_SESSION['access_token']['screen_name'],$admin_screen_name))) {
	$_SESSION['notice'] = "Only administrators are allowed to stop / start archiving processes";
	header('Location:index.php');
	die;
}

// Kill any jobs that might be left hanging
$kill = "kill -9 `ps -ef |grep yourtwapperkeeper|grep -v grep | awk '{print $2}'`";
exec($kill);

//JN : process rawstream records
$count = 0;
$script_key = uniqid();

//test MySQL and PGSQL databases
$q_test = "select 1";

//JN : first, clear rawstream (in case of brutal stop)
do
{
	$r_test_mysql = true;
	$r_test_pg = true;

	$r_test_mysql = mysql_query($q_test, $db->connection);

	//test pgdb connection only if PGSQL_INSERT == true
	if(PGSQL_INSERT == true)
	{
		$r_test_pg = pg_query($pgdb->connection, $q_test);
	}

	//if PGSQL_INSERT == false, $r_test_pg = true
	if(!$r_test_mysql || !$r_test_pg)
	{		
		if(!$r_test_mysql)
		{
			fputs($log, "ERROR : MYSQL DATABASE IS DOWN :\n");
			fputs($log, "\tQuery : ".$q_test."\n");
			fputs($log, "\tError : ".mysql_error()."\n");
			fputs($log, "\tTime : ".time()."\n");
			fputs($log, "\n");
		}
		
		if(!$r_test_pg)
		{
			fputs($log, "ERROR : POSTGRESQL DATABASE IS DOWN :\n");
			fputs($log, "\tQuery : ".$q_test."\n");
			fputs($log, "\tTime : ".time()."\n");
			fputs($log, "\n");
		}
		
		//break do-while loop (DB(s) is(are) down)
		$count = 0;
	}
	else
	{
		//JN : flag <> $script_key instead of = '-1' because ytk_stream.php may have locked some tweets with an other flag (uniqid())
		$q = "update rawstream set flag = '$script_key' where flag <> '$script_key' limit $stream_process_stack_size";
		$r= mysql_query($q, $db->connection);
		
		if(!$r)
		{
			fputs($log, "ERROR : UPDATE RAWSTREAM FAILED :\n");
			fputs($log, mysql_error()."\n");
			fputs($log, "\tQuery : ".$q."\n");
			fputs($log, "\tTime : ".time()."\n");
			fputs($log, "\n");
		}

		// get keyword into memory
		$q = "select id,keyword,account_id from archives";
		$r = mysql_query($q, $db->connection);
		
		if(!$r)
		{
			fputs($log, "ERROR : SELECT FROM ARCHIVES FAILED :\n");
			fputs($log, mysql_error()."\n");
			fputs($log, "\tQuery : ".$q."\n");
			fputs($log, "\tTime : ".time()."\n");
			fputs($log, "\n");
		}
		
		$preds = array();
		
		//JN : get keyword and account_id
		while ($row = mysql_fetch_assoc($r)) {
			$preds[$row['id']] = array($row['keyword'], $row['account_id']);
		}
		//JN
		
		// grab the locked up tweets and load into memory
		$q = "select * from rawstream where flag = '$script_key'";
		$r = mysql_query($q, $db->connection);
		
		if(!$r)
		{
			fputs($log, "ERROR : SELECT FROM RAWSTREAM FAILED :\n");
			fputs($log, mysql_error()."\n");
			fputs($log, "\tQuery : ".$q."\n");
			fputs($log, "\tTime : ".time()."\n");
			fputs($log, "\n");
		}

		$count = mysql_num_rows($r);
		fputs($log, "Number of rows in rawstream : ".$count." ");
		
		//JN
		
		$batch = array();
		while ($row = mysql_fetch_assoc($r)) {
			$batch[] = $row;
		}
		
		fputs($log, "Beginning of tweets processing\n");
		
		// for each tweet in memory, compare against predicates and insert
		foreach ($batch as $tweet) {

			fputs($log, $tweet['id']."[".time()."](");
			
			foreach ($preds as $ztable=>$data)
			{
				$keyword = $data[0];
				$account_id = $data[1];
				
				//~ if (stristr(($tweet['text']." ".$tweet['urls']." ".$tweet['medias_urls']), $keyword) == TRUE) {
				if (stripos(($tweet['text']." ".$tweet['initial_tweet_text']." ".$tweet['urls']." ".$tweet['medias_urls']), $keyword) !== FALSE) {
					
					fputs($log, "KW:".$keyword.":");
					
					$q_check = "select id from z_".$ztable." where id = '".$tweet['id']."'";
					$result_check = mysql_query($q_check, $db->connection);
					
					//check duplicate records
					if (mysql_num_rows($result_check)==0)
					{
						fputs($log, "INSERT,");
						
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
							fputs($log, "\n\nERROR : MYSQL - INSERT :\n");
							fputs($log, mysql_error()."\n");
							fputs($log, "Query : ".$q_insert."\n");
							fputs($log, "\tTime : ".time()."\n");
							fputs($log, "\n\n");
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
								fputs($log, "\n\nERROR : PGSQL - INSERT :\n");
								fputs($log, "Query : ".$q_insert."\n");
								fputs($log, "\tTime : ".time()."\n");
								fputs($log, "\n\n");
							}
						}
					}
					else
					{
						fputs($log, "DUPLICATE,");
					}
					
				}
				
				//JN : if the tweet is from a followed user
				if ($tweet['from_user_id'] === $account_id) {

					fputs($log, "UID:".$account_id.":");
					
					$q_check = "select id from z_".$ztable." where id = '".$tweet['id']."'";
					$result_check = mysql_query($q_check, $db->connection);
					
					//check duplicate records
					if (mysql_num_rows($result_check)==0)
					{
						fputs($log, "INSERT,");
						
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
							fputs($log, "\n\nERROR : MYSQL - INSERT :\n");
							fputs($log, mysql_error()."\n");
							fputs($log, "Query : ".$q_insert."\n");
							fputs($log, "\tTime : ".time()."\n");
							fputs($log, "\n\n");
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
								fputs($log, "\n\nERROR : PGSQL - INSERT :\n");
								fputs($log, "Query : ".$q_insert."\n");
								fputs($log, "\tTime : ".time()."\n");
								fputs($log, "\n\n");
							}
						}
					}
					else
					{
						fputs($log, "DUPLICATE,");
					}
				}
			}
			
			fputs($log, "),");
		}
		
		fputs($log, "\nEnd of tweets processing\n\n");
		
		// delete tweets in flag
		$q = "delete from rawstream where flag = '$script_key'";
		$r_delete = mysql_query($q, $db->connection);
		
		if (!$r_delete)
		{
			fputs($log, "\n\nERROR : MYSQL - DELETE FROM RAWSTREAM :\n");
			fputs($log, "Query : ".$q."\n");
			fputs($log, "\tTime : ".time()."\n");
			fputs($log, "\n\n");
		}
		
		// update counts
		foreach ($preds as $ztable=>$keyword) {
			$q_count = "select count(id) from z_$ztable";
			$r_count = mysql_query($q_count, $db->connection);
			$r_count = mysql_fetch_assoc($r_count);
			
			$q_update = "update archives set count = '".$r_count['count(id)']."' where id = '$ztable'";
			$r_update = mysql_query($q_update, $db->connection);
			
			if (!$r_update)
			{
				fputs($log, "\n\nERROR : MYSQL - UPDATE ARCHIVES :\n");
				fputs($log, "Query : ".$q."\n");
				fputs($log, "\tTime : ".time()."\n");
				fputs($log, "\n\n");
			}
		}
	}
}
while($count <> 0);

// List of archiving scripts
$cmd = $archive_process_array;

// Start and register jobs
$pid = '';
$pids = '';
foreach ( $cmd as $key=>$value ) {
	$job = 'php '.$tk_your_dir.$value;
	$pid = $tk->startProcess($job);
	$pids .= $pid.",";
	mysql_query("update processes set pid = '$pid' where process = '$value'", $db->connection);
}
$pids = substr($pids, 0, -1);

$contents = ob_get_flush();
file_put_contents("log/startarchiving.log",$contents);

ob_get_clean();

$_SESSION['notice'] = "Twitter archiving processes have been started. (PIDs = $pids)";
header('Location:index.php');
?>

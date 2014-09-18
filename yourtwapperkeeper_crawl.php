<?php
// load important files
require_once('config.php');
require_once('function.php');
require_once('twitteroauth.php');

// setup values
$pid = getmypid();
// time to sleep between 2 queries (11 s.)
//$sleep = $twitter_api_sleep_min;

//180 queries = 15 minutes <=> 1 query = 5 s
//in any case, we can't exceed the rate
$sleep = 5;

$count = 0;

$log = fopen("log/yourtwapperkeeper_crawl_fail_pg.log", "w");

// Setup connection
$connection = new TwitterOAuth($tk_oauth_consumer_key, $tk_oauth_consumer_secret, $tk_oauth_token, $tk_oauth_token_secret); 
$connection->useragent = $youtwapperkeeper_useragent; 

//JN : get limit rate for search/tweets
$nb_queries = 0;  

$q_rate_limit = $connection->get('application/rate_limit_status', array('resources' => 'search'));
$res_rate_limit = get_object_vars($q_rate_limit);
$res_rate_limit_search = get_object_vars($res_rate_limit['resources']->search);

//number of remaining queries
$nb_remaining_queries = $res_rate_limit_search['/search/tweets']->remaining;
//reset timestamp (the window is reset each 15 minutes)
$reset_limit_rate = $res_rate_limit_search['/search/tweets']->reset;

echo "yourtwapperkeeper_crawl\n\n";

echo "Remaining queries : ".$nb_remaining_queries."\n";
echo "Reset at : ".$reset_limit_rate." (".date('d/m/Y_H:i:s', $reset_limit_rate).")\n\n";

//test query for pgdb database
$q_test = "select 1";

//JN

while (true) {
	// Query for archives
	$q_archives = "select * from archives order by no_smallest_tweet_id, id";
	$r_archives = mysql_query($q_archives, $db->connection);
	
	echo "Querying for ".mysql_num_rows($r_archives)." archives\n\n";
	
	while ($row_archives = mysql_fetch_assoc($r_archives)) {

		echo "Archive ".$row_archives['id']." - ".$row_archives['keyword']."\n";
		
		// sleep for rate limiting
		echo "Sleep for rate limiting = ".$sleep."\n";
		sleep($sleep);

		//get smallest and largest ids from this archive
		$q_ids = "select smallest_tweet_id, largest_tweet_id, no_smallest_tweet_id from archives where id = '".$row_archives['id']."'";
		$r_ids = mysql_query($q_ids, $db->connection);

		$row = mysql_fetch_assoc($r_ids);
		
		$largest_id = $row['largest_tweet_id'];
		$smallest_id = $row['smallest_tweet_id'];
		$no_smallest_tweet_id = $row['no_smallest_tweet_id'];
		
		echo "largest_tweet_id = ".$largest_id."\n";
		echo "smallest_tweet_id = ".$smallest_id."\n";
		echo "no_smallest_tweet_id = ".$no_smallest_tweet_id."\n";
		
		//to manage pages
		$more_pages = false;
		$more_pages_max_id = 0;

		$largest_search_id = '';
		
		echo "\n";
		
		//JN
		do
		{
			//JN : do queries ONLY if limit rate is respected
			if($nb_queries < $nb_remaining_queries)
			{
				echo "Rate limit ok : ".$nb_queries." < ".$nb_remaining_queries." (remaining queries)\n\n";
				
				//if not all old tweets have been received, $no_smallest_tweet_id == false
				if($no_smallest_tweet_id == false)
				{
					echo "Not all old tweets have been received; ";
					
					//if $smallest_id === '', this is the first time we check tweets for this archive
					if($smallest_id === '')
					{
						echo "it is the first time we check tweets for this archive\n";
						
						//JN : if type == Account, search for the tweets without '@' to receive tweets from this user
						if($row_archives['type'] === 'Account')
						{
							$search = $connection->get('search/tweets', array('q' => substr($row_archives['keyword'], 1), 'count'=>100, 'max_id'=>111111111111111111111111111111111));
							
							echo "Query (Account) : \n";
							print_r(array('q' => substr($row_archives['keyword'], 1), 'count'=>100));
							echo "\n";
						}
						elseif($row_archives['type'] === 'Keyword')
						{
							$search = $connection->get('search/tweets', array('q' => '"'.$row_archives['keyword'].'"', 'count'=>100, 'max_id'=>111111111111111111111111111111111));
							
							echo "Query (Keyword) : \n";
							print_r(array('q' => '"'.$row_archives['keyword'].'"', 'count'=>100));
							echo "\n";
						}
						else
						{
							$search = $connection->get('search/tweets', array('q' => $row_archives['keyword'], 'count'=>100, 'max_id'=>111111111111111111111111111111111));
							
							echo "Query (Hashtag) : \n";
							print_r(array('q' => $row_archives['keyword'], 'count'=>100));
							echo "\n";
						}

					}
					
					//else, we have already got 100 tweets, we are now going to use max_id
					//check the tweets having a smaller id (so, older) than the oldest tweet we got
					else
					{
						echo "we already have tweets for this archive\n";
						
						//JN : if type == Account, search for the tweets without '@' to receive tweets from this user
						if($row_archives['type'] === 'Account')
						{
							$search = $connection->get('search/tweets', array('q' => substr($row_archives['keyword'], 1), 'count'=>100, 'max_id'=>$smallest_id));
							
							echo "Query (Account) : \n";
							print_r(array('q' => substr($row_archives['keyword'], 1), 'count'=>100, 'max_id'=>$smallest_id));
							echo "\n";
						}
						elseif($row_archives['type'] === 'Keyword')
						{
							$search = $connection->get('search/tweets', array('q' => '"'.$row_archives['keyword'].'"', 'count'=>100, 'max_id'=>$smallest_id));
							
							echo "Query (Keyword) : \n";
							print_r(array('q' => '"'.$row_archives['keyword'].'"', 'count'=>100, 'max_id'=>$smallest_id));
							echo "\n";
						}
						else
						{
							$search = $connection->get('search/tweets', array('q' => $row_archives['keyword'], 'count'=>100, 'max_id'=>$smallest_id));
							
							echo "Query (Hashtag) : \n";
							print_r(array('q' => $row_archives['keyword'], 'count'=>100, 'max_id'=>$smallest_id));
							echo "\n";
						}
					}
					
					//if the search does not return any tweet, that means that we got all tweets we can get (from 1 week max)
					//now, we are not going to search for recent tweets
					//set no_smallest_tweet_id to true
					$searchresult = get_object_vars($search);
					$count = count($searchresult['statuses']);
					
					echo "Returned ".$count." results\n";
					
					if($count === 0)
					{
						echo "SEARCH : \n";
						print_r($search);
						echo "SEARCHRESULT : \n";
						print_r($searchresult);
						echo "\n\n";
						echo "=> update no_smallest_tweet_id = true for this archive\n";
						
						$q_update_archives_id = "update archives set no_smallest_tweet_id = true where id = '".$row_archives['id']."'";
						mysql_query($q_update_archives_id,$db->connection);
					}
					
				}
				
				//else, no_smallest_tweet_id == true, so we can't get older tweets
				//so focus now on recent tweets
				else
				{
					echo "All old tweets have been received; ";
					
					//if there are not more pages, we search tweets more recently than the most recent we have (with since_id)
					if($more_pages == false)
					{
						echo "there are no pages\n";
						
						//JN : if type == Account, search for the tweets without '@' to receive tweets from this user
						if($row_archives['type'] === 'Account')
						{
							$search = $connection->get('search/tweets', array('q' => substr($row_archives['keyword'], 1), 'count'=>100, 'since_id'=>$largest_id));
							
							echo "Query (Account) : \n";
							print_r(array('q' => substr($row_archives['keyword'], 1), 'count'=>100, 'since_id'=>$largest_id));
							echo "\n";
						}
						elseif($row_archives['type'] === 'Keyword')
						{
							$search = $connection->get('search/tweets', array('q' => '"'.$row_archives['keyword'].'"', 'count'=>100, 'since_id'=>$largest_id));
							
							echo "Query (Keyword) : \n";
							print_r(array('q' => '"'.$row_archives['keyword'].'"', 'count'=>100, 'since_id'=>$largest_id));
							echo "\n";
						}
						else
						{
							$search = $connection->get('search/tweets', array('q' => $row_archives['keyword'], 'count'=>100, 'since_id'=>$largest_id));
							
							echo "Query (Hashtag) : \n";
							print_r(array('q' => $row_archives['keyword'], 'count'=>100, 'since_id'=>$largest_id));
							echo "\n";
						}
					}
					//else, use max_id and since_id to retrieve missed tweets
					else
					{
						echo "there are more pages\n";
						
						if($row_archives['type'] === 'Account')
						{
							$search = $connection->get('search/tweets', array('q' => substr($row_archives['keyword'], 1), 'count'=>100, 'since_id'=>$largest_id, 'max_id'=>$more_pages_max_id));
							
							echo "Query (Account) : \n";
							print_r(array('q' => substr($row_archives['keyword'], 1), 'count'=>100, 'since_id'=>$largest_id, 'max_id'=>$more_pages_max_id));
							echo "\n";
						}
						elseif($row_archives['type'] === 'Keyword')
						{
							$search = $connection->get('search/tweets', array('q' => '"'.$row_archives['keyword'].'"', 'count'=>100, 'since_id'=>$largest_id, 'max_id'=>$more_pages_max_id));
							
							echo "Query (Keyword) : \n";
							print_r(array('q' => '"'.$row_archives['keyword'].'"', 'count'=>100, 'since_id'=>$largest_id, 'max_id'=>$more_pages_max_id));
							echo "\n";
						}
						else
						{
							$search = $connection->get('search/tweets', array('q' => $row_archives['keyword'], 'count'=>100, 'since_id'=>$largest_id, 'max_id'=>$more_pages_max_id));
							
							echo "Query (Hashtag) : \n";
							print_r(array('q' => $row_archives['keyword'], 'count'=>100, 'since_id'=>$largest_id, 'max_id'=>$more_pages_max_id));
							echo "\n";
						}
					}

					$searchresult = get_object_vars($search);
					$count = count($searchresult['statuses']);
					
					echo "Returned ".$count." results\n";
					
					//if the query returns more than 1 tweet
					//maybe it's because there are more pages
					//if it's not, we lost a query but we are sure that we didn't miss any tweet
					if($count <> 0)
					{
						echo "Maybe there are more pages\n";
						$more_pages = true;
					}
					else
					{
						echo "There are no more pages\n";
						$more_pages = false;
					}
				}
				
				//JN : a query has been sent (for rate limit)
				$nb_queries++;
				
				echo "Beginning of tweets processing\n";
				
				//test DB connections
				$r_test_mysql = true;
				$r_test_pg = true;
				
				$r_test_mysql = mysql_query($q_test, $db->connection);
				
				//test pgdb connection only if PGSQL_INSERT == true
				if(PGSQL_INSERT == true)
				{
					$r_test_pg = pg_query($pgdb->connection, $q_test);
				}

				//if PGSQL_INSERT == false, $r_test = true (jump to else)
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
						$die_message = $die_message . "yourtwapperkeeper_crawl.php has been killed (MySQL and PgSQL DBs are down)";
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
							$die_message = $die_message . "yourtwapperkeeper_crawl.php has been killed (MySQL DB is down)";
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
							$die_message = $die_message . "yourtwapperkeeper_crawl.php has been killed (PG DB is down)";
							mail("tee1@depinfo.u-bourgogne.fr", "YTK", $die_message);
							die($die_message);
						}
					}
				}
				//else, DB connections are still available
				//(useless else because else, process had been killed)
				else
				{
					// parse results
					foreach ($searchresult['statuses'] as $key=>$value)
					{

						$value = get_object_vars($value);

						if($no_smallest_tweet_id == true && $more_pages == true && $largest_search_id === '')
						{
							$largest_search_id = $value['id_str'];
							echo "First tweet : ".$largest_search_id."\n";
						}
						
						echo $value['id_str'].",";

						// extract data
						//extract($value,EXTR_PREFIX_ALL,'temp');
						$temp_text = $value['text'];
						$temp_to_user_id = $value['in_reply_to_user_id'];
						$temp_from_user = $value['user']->screen_name;
						
						$temp_id = $value['id_str'];
						$temp_from_user_id = $value['user']->id;

						$temp_iso_language_code = $value['lang'];
						$temp_source = $value['source'];
						//$temp_profile_image_url = $value['user']->profile_background_image_url;
						// modification JN
						$temp_profile_image_url = $value['user']->profile_image_url;
						$temp_created_at = $value['created_at'];
						
						//JN : values for new attributes
						$temp_from_user_name = $value['user']->name;
						$temp_from_user_location = $value['user']->location;
						$temp_from_user_url = $value['user']->url;
						$temp_from_user_description = $value['user']->description;
						$temp_from_user_created_at = $value['user']->created_at;
						
						$user = get_object_vars($value['user']);
						
						if($user['verified'] == false){
							$temp_from_user_verified = 0;
						}
						else{
							$temp_from_user_verified = 1;
						}
						
						if($user['contributors_enabled'] == false){
							$temp_from_user_contributors_enabled = 0;
						}
						else{
							$temp_from_user_contributors_enabled = 1;
						}
						
						if($value['truncated'] == false){
							$temp_truncated = 0;
						}
						else{
							$temp_truncated = 1;
						}
						
						$temp_in_reply_to_status_id = $value['in_reply_to_status_id'];
						
						$temp_contributors = '';
						
						if(array_key_exists('contributors', $value)){
							$contributors = $value['contributors'];

							$i = 0;
							
							//for each contributor of this tweet
							//getting its id and screen_name
							for($i = 0; $i < count($contributors); $i++) {
								$temp_contributors .= $contributors[$i]->id.":".$contributors[$i]->screen_name;
								
								if($i <> count($contributors) - 1){
									$temp_contributors .= "\n";
								}
							}
						}
						
						//if the tweet is a RT
						if(array_key_exists('retweeted_status', $value)){
							$temp_initial_tweet_id = $value['retweeted_status']->id;
							$temp_initial_tweet_text = $value['retweeted_status']->text;
							$temp_initial_tweet_user = $value['retweeted_status']->user->id.":".$value['retweeted_status']->user->screen_name.":".$value['retweeted_status']->user->name;
							$temp_initial_tweet_time = strtotime($value['retweeted_status']->created_at);
							
							//getting `entities` from $value['retweeted_status']
							//(avoid having original tweet user's name in mentions)
							$entities = get_object_vars($value['retweeted_status']->entities);
							//getting `user_mentions` from $entities
							$user_mentions = $entities['user_mentions'];
							
							//for each user mentionned, getting its id, screen_name (@xxx) and name
							$temp_user_mentions = '';

							$i = 0;
							
							for($i = 0; $i < count($user_mentions); $i++) {
								$temp_user_mentions .= $user_mentions[$i]->id . ":";
								$temp_user_mentions .= $user_mentions[$i]->screen_name . ":";
								$temp_user_mentions .= $user_mentions[$i]->name;
								
								if($i <> count($user_mentions) - 1){
									$temp_user_mentions .= "\n";
								}
							}
						}
						//if the tweet is not a RT
						else{
							$temp_initial_tweet_id = NULL;
							$temp_initial_tweet_text = NULL;
							$temp_initial_tweet_user = NULL;
							$temp_initial_tweet_time = 0;
							
							//getting `entities` from $value
							$entities = get_object_vars($value['entities']);
							//getting `user_mentions` from $entities
							$user_mentions = $entities['user_mentions'];
							
							//for each user mentionned, getting its id, screen_name (@xxx) and name
							$temp_user_mentions = '';

							$i = 0;
							
							for($i = 0; $i < count($user_mentions); $i++) {
								$temp_user_mentions .= $user_mentions[$i]->id . ":";
								$temp_user_mentions .= $user_mentions[$i]->screen_name . ":";
								$temp_user_mentions .= $user_mentions[$i]->name;
								
								if($i <> count($user_mentions) - 1){
									$temp_user_mentions .= "\n";
								}
							}
						}

						//getting entities from tweet (not original tweet if RT)
						$entities = get_object_vars($value['entities']);
						
						//URLS
						$urls = $entities['urls'];
					
						$temp_urls = '';
						$i = 0;
						
						//for each url used in the tweet
						//getting its expanded_url
						for($i = 0; $i < count($urls); $i++) {
							$temp_urls .= $urls[$i]->expanded_url;
							
							if($i <> count($urls) - 1){
								$temp_urls .= "\n";
							}
						}
						
						//MEDIAS
						$temp_medias_urls = '';
						
						if(array_key_exists('media', $entities)){
							$media = $entities['media'];

							$i = 0;
							
							//for each media used in the tweet
							//getting its expanded_url
							for($i = 0; $i < count($media); $i++) {
								$temp_medias_urls .= $media[$i]->expanded_url;
								
								if($i <> count($media) - 1){
									$temp_medias_urls .= "\n";
								}
							}
						}
						
						//HASHTAGS
						$hashtags = $entities['hashtags'];

						$temp_hashtags = '';
						$i = 0;
						
						//for each hashtag used in the tweet
						//getting its text
						for($i = 0; $i < count($hashtags); $i++) {
							$temp_hashtags .= $hashtags[$i]->text;
							
							if($i <> count($hashtags) - 1){
								$temp_hashtags .= "\n";
							}
						}
						
						//SYMBOLS
						$symbols = $entities['symbols'];

						$temp_symbols = '';
						$i = 0;
						
						//for each symbol used in the tweet
						//getting its text
						for($i = 0; $i < count($symbols); $i++) {
							$temp_symbols .= $symbols[$i]->text;
							
							if($i <> count($symbols) - 1){
								$temp_symbols .= "\n";
							}
						}
					
						//JN
						
						// extract geo information
						if ($value['geo'] != NULL) {
							$geo = get_object_vars($value['geo']);
							$geo_type = $geo['type'];
							$geo_coordinates_0 = $geo['coordinates'][0];
							$geo_coordinates_1 = $geo['coordinates'][1];
						} else {
							$geo_type = NULL;
							$geo_coordinates_0 = 0;
							$geo_coordinates_1 = 0;
						}
						
						// duplicate record check and insert into proper cache table if not a duplicate
						$q_check = "select id from z_".$row_archives['id']." where id = '".$value['id']."'";
						$result_check = mysql_query($q_check, $db->connection);
					
						if (mysql_numrows($result_check)==0) {
							
							//JN : add values for new attributes
							$q = "insert into z_".$row_archives['id']." values ('search','".mysql_real_escape_string($temp_text)."','$temp_to_user_id','$temp_from_user','$temp_id','$temp_from_user_id','$temp_iso_language_code','".mysql_real_escape_string($temp_source)."','$temp_profile_image_url','$geo_type','$geo_coordinates_0','$geo_coordinates_1','$temp_created_at','".strtotime($temp_created_at)."','".mysql_real_escape_string($temp_from_user_name)."','".mysql_real_escape_string($temp_from_user_location)."','".mysql_real_escape_string($temp_from_user_url)."','".mysql_real_escape_string($temp_from_user_description)."','$temp_from_user_created_at','$temp_from_user_verified','$temp_from_user_contributors_enabled','$temp_truncated','$temp_in_reply_to_status_id','$temp_contributors','$temp_initial_tweet_id','".mysql_real_escape_string($temp_initial_tweet_text)."','".mysql_real_escape_string($temp_initial_tweet_user)."','$temp_initial_tweet_time','".mysql_real_escape_string($temp_user_mentions)."','".mysql_real_escape_string($temp_urls)."','".mysql_real_escape_string($temp_medias_urls)."','$temp_hashtags','$temp_symbols')";
							//JN
							
							$result_insert = mysql_query($q, $db->connection);
							
							if (!$result_insert) {
								echo "ERROR : INSERTION INTO Z_".$row_archives['id']." FAILED :\n";
								echo mysql_error()."\n";
								echo "\tQuery : ".$q."\n";
								echo "\tTime : ".time()."\n";
								echo "\n";
							}
							
							if(PGSQL_INSERT == true)
							{
								//JN : query for PGSQL
								$q2 = "insert into gtweets values ('search','".pg_escape_string($temp_text)."','$temp_to_user_id','$temp_from_user','$temp_id','$temp_from_user_id','$temp_iso_language_code','".pg_escape_string($temp_source)."','$temp_profile_image_url','$geo_type','$geo_coordinates_0','$geo_coordinates_1','$temp_created_at','".strtotime($temp_created_at)."','".pg_escape_string($temp_from_user_name)."','".pg_escape_string($temp_from_user_location)."','".pg_escape_string($temp_from_user_url)."','".pg_escape_string($temp_from_user_description)."','$temp_from_user_created_at','$temp_from_user_verified','$temp_from_user_contributors_enabled','$temp_truncated','$temp_in_reply_to_status_id','$temp_contributors','$temp_initial_tweet_id','".pg_escape_string($temp_initial_tweet_text)."','".pg_escape_string($temp_initial_tweet_user)."','$temp_initial_tweet_time','".pg_escape_string($temp_user_mentions)."','".pg_escape_string($temp_urls)."','".pg_escape_string($temp_medias_urls)."','$temp_hashtags','$temp_symbols','".pg_escape_string($row_archives['keyword'])."', '".$youtwapperkeeper_useragent."')";
								//JN
								
								//echo $q2;
								$r2 = pg_query($pgdb->connection, $q2);
								
								if(!$r2)
								{
									echo "ERROR : INSERTION INTO GTWEETS FAILED :\n";
									echo "\tQuery : ".$q2."\n";
									echo "\tTime : ".time()."\n";
									echo "\n";
									fputs($log, $q2."\n\n");
								}
							}
							
							//echo "[".$row_archives['id']."-".$row_archives['keyword']."] $page_counter - $temp_id - insert\n";
						} else {
							echo "\n\tduplicate\n";
						}
					
						if($no_smallest_tweet_id == false)
						{
							$smallest_id = $temp_id - 1; // resetting to lowest tweet id //JN -1
						}
						
						if($more_pages == true)
						{
							$more_pages_max_id = $temp_id-1;
						}
					}

					echo "\nEnd of tweets processing\n\n";
					
					//if more_pages == false, change largest_id because we got all tweets
					if($more_pages == false)
					{
						if($largest_search_id <> '')
						{
							$largest_id = $largest_search_id;
							echo "We have all tweets older than ".$largest_id." (new largest_id)\n";
						}
					}
					
					//else, DON'T CHANGE largest_id because it is used to get more pages
					else
					{
						echo "We don't have all tweets\n";
					}
					
					if($no_smallest_tweet_id == false)
					{
						//get max(id) from this archive to set largest_id
						$q_max = "select max(id) from z_".$row_archives['id']." where archivesource='search'";
						$r_max = mysql_query($q_max, $db->connection);

						$row = mysql_fetch_assoc($r_max);
						$largest_id = $row['max(id)'];
						
						"New largest id : ".$largest_id."\n";
					}
					
					echo "Update archives : ".$smallest_id."(smallest_id), ".$largest_id."(largest_id)\n\n";
					
					// update counts and set smallest_id and largest_id in archives
					$q_count_total = "select count(id) from z_".$row_archives['id'];
					$r_count_total = mysql_query($q_count_total, $db->connection);  
					$r_count_total = mysql_fetch_assoc($r_count_total);
					$q_update_count_total = "update archives set count = '".$r_count_total['count(id)']."', smallest_tweet_id = '".$smallest_id."', largest_tweet_id = '".$largest_id."' where id = '".$row_archives['id']."'";
					$r_query = mysql_query($q_update_count_total,$db->connection);
					
					if(!$r_query)
					{
						echo "ERROR : UPDATE ARCHIVES QUERY :\n";
						echo mysql_error()."\n";
						echo "\n\n";
					}
				
					// update pid and last_ping in process table
					mysql_query("update processes set last_ping = '".time()."' where pid = '$pid'", $db->connection);
					
					if(!$r_query)
					{
						echo "ERROR : UPDATE PROCESSES QUERY :\n";
						echo mysql_error()."\n";
						echo "\n\n";
					}
				}
			}
			
			//JN : else, we can't query, because we have exceeded the limit rate
			else
			{
				echo "Rate limit exceeded : ".$nb_queries." >= ".$nb_remaining_queries." (remaining queries)\n\n";
				
				//calculate waiting time (time left to reset_limit_rate)
				$timestamp = date_timestamp_get(date_create());
				$waiting_time = $reset_limit_rate-$timestamp;
				
				echo $reset_limit_rate." - ".$timestamp." = ".$waiting_time." seconds\n";
				
				//if waiting_time > 0, then we must wait because we reached the limit rate
				if($waiting_time > 0)
				{
					//in fact, we are going to wait 10 seconds more, as a precaution
					$waiting_time = $waiting_time + 10;

					//sleep
					echo "Wait for ".$waiting_time." seconds\n";
					sleep($waiting_time);
					echo "Let's go\n";
					
					//get new limit rate (usually 180) and reset time (15 minutes later)
					$nb_queries = 0;  

					$q_rate_limit = $connection->get('application/rate_limit_status', array('resources' => 'search'));
					$res_rate_limit = get_object_vars($q_rate_limit);
					$res_rate_limit_search = get_object_vars($res_rate_limit['resources']->search);

					$nb_remaining_queries = $res_rate_limit_search['/search/tweets']->remaining;
					$reset_limit_rate = $res_rate_limit_search['/search/tweets']->reset;
				}
				
				//else, no time to waste, we reached the limit but we used queries on the next 15 minutes window
				else
				{
					echo "Do not wait\n";
					
					$nb_queries = 0;

					$q_rate_limit = $connection->get('application/rate_limit_status', array('resources' => 'search'));
					$res_rate_limit = get_object_vars($q_rate_limit);
					$res_rate_limit_search = get_object_vars($res_rate_limit['resources']->search);

					$nb_remaining_queries = $res_rate_limit_search['/search/tweets']->remaining;
					$reset_limit_rate = $res_rate_limit_search['/search/tweets']->reset;
				}
				
				//set $count = 1 to loop
				$count = 1;
			}

		}while($count <> 0);

		echo "\n------------------------------ No more tweets for this archive ------------------------------\n\n";
	
	}

}

?>


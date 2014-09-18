<?php
require_once('Phirehose.php');
require_once('OauthPhirehose.php');
require_once('config.php');
require_once('function.php');

define('TWITTER_CONSUMER_KEY',$tk_oauth_consumer_key);
define('TWITTER_CONSUMER_SECRET',$tk_oauth_consumer_secret);

class DynamicTrackConsumer extends OauthPhirehose
{ 

	public function enqueueStatus($status)
	{
		global $db;
		global $pgdb;

		$status = json_decode($status);
		$status = get_object_vars($status);

		//~ print_r($status);
		
		//JN : if the JSON string received is not a tweet
		if(!array_key_exists('id', $status))
		{
			echo "ERROR : JSON STRING IS NOT A TWEET :\n";
			print_r($status);
			echo "\n\n";
			
			//if the JSON is a deletion message
			if(array_key_exists('delete', $status))
			{
				//get ID of the message
				$delete = $status['delete'];
				$delete_obj = get_object_vars($delete);
				$delete_status_id = $delete_obj['status']->id;
				
				echo "ID : ".$delete_status_id."\n";
				
				//delete it from rawstream
				$q_delete = "delete from rawstream where id = '".$delete_status_id."'";
				$r_delete = mysql_query($q_delete, $db->connection);

				if(!$r_delete)
				{
					echo "ERROR : DELETION FROM RAWSTREAM FAILED :\n";
					echo mysql_error()."\n";
					echo "\tQuery : ".$q_delete."\n";
					echo "\tTweet id : ".$delete_status_id."\n";
					echo "\tTime : ".time()."\n";
					echo "\n";
				}
				else
				{
					if(mysql_affected_rows($db->connection) === 0)
					{
						echo "TWEET ".$delete_status_id." IS NOT IN RAWSTREAM\n";
					}
					else
					{
						echo "TWEET ".$delete_status_id." HAS BEEN DELETED FROM RAWSTREAM (".mysql_affected_rows($db->connection)." LINE(S))\n";
					}
				}
				
				//only if tweet has not been deleted from rawstream (it was added to z_xxx)
				if(mysql_affected_rows($db->connection) == 0)
				{
					//get all archives' id
					$q_arch = "select id from archives";
					$r_arch = mysql_query($q_arch, $db->connection);
					
					if(!$r_arch)
					{
						echo "ERROR : SELECT ID FROM ARCHIVES FAILED :\n";
						echo mysql_error()."\n";
						echo "\tQuery : ".$q_arch."\n";
						echo "\tTime : ".time()."\n";
						echo "\n";
					}
					else
					{
						//for each archive
						while ($row_arch = mysql_fetch_assoc($r_arch))
						{
							//try to delete the tweet
							$q_delete = "delete from z_".$row_arch['id']." where id = '".$delete_status_id."'";
							$r_delete = mysql_query($q_delete, $db->connection);
							
							if(!$r_delete)
							{
								echo "ERROR : DELETION FROM Z_".$row_arch['id']." FAILED :\n";
								echo mysql_error()."\n";
								echo "\tQuery : ".$q_delete."\n";
								echo "\tTweet id : ".$delete_status_id."\n";
								echo "\tTime : ".time()."\n";
								echo "\n";
							}
							else
							{
								if(mysql_affected_rows($db->connection) === 0)
								{
									echo "TWEET ".$delete_status_id." IS NOT IN Z_".$row_arch['id']."\n";
								}
								else
								{
									echo "TWEET ".$delete_status_id." HAS BEEN DELETED FROM Z_".$row_arch['id']."\n";
									
									$q_update = "update archives set count = (select count(id) from z_".$row_arch['id'].") where id = '".$row_arch['id']."'";
									$r_update = mysql_query($q_update, $db->connection);
									
									if(!$r_update)
									{
										echo "ERROR : UPDATE ARCHIVE COUNT FAILED :\n";
										echo mysql_error()."\n";
										echo "\tQuery : ".$q_update."\n";
										echo "\tTime : ".time()."\n";
										echo "\n";
									}
									else
									{
										echo "COUNT FOR ARCHIVE ".$row_arch['id']." HAS BEEN UPDATED\n";
									}
								}
							}
						}
					}
					
					//finally, delete the tweet from PGSQL DB
					if(PGSQL_INSERT == true)
					{
						$q_delete = "delete from gtweets where id = '".$delete_status_id."'";
						$r_delete = pg_query($pgdb->connection, $q_delete);
						
						if(!$r_delete)
						{
							echo "ERROR : DELETION FROM GTWEETS FAILED :\n";
							echo "\tQuery : ".$q_delete."\n";
							echo "\tTime : ".time()."\n";
							echo "\n";
						}
						else
						{
							if(pg_affected_rows($r_delete) === 0)
							{
								//normally, unreached statement
								echo "TWEET ".$delete_status_id." IS NOT IN GTWEETS\n";
							}
							else
							{
								echo "TWEET ".$delete_status_id." HAS BEEN DELETED FROM GTWEETS (".pg_affected_rows($r_delete)." LINE(S))\n";
							}
						}
					}
				}
				//else, the tweet has been deleted before it was inserted into z_xxx
				else
				{
					echo "TWEET ".$delete_status_id." HAS NOT BEEN ADDED INTO Z_xxx\n";
				}
			}
		}
		else
		{
			if ($status['id'] <> NULL) {	
				$values_array = array();
				
				//JN : checking if not null to avoid warning from get_object_vars
				$geo = null;

				if($status['geo'] <> NULL){
					$geo = get_object_vars($status['geo']);
				}
				//JN
				
				$user = get_object_vars($status['user']);
				
				$values_array[] = "-1";                                     // processed_flag [-1 = waiting to be processed]
				$values_array[] = mysql_real_escape_string($status['text']);// text
				$values_array[] = $status['in_reply_to_user_id'];           // to_user_id
				$values_array[] = $user['screen_name'];                     // from_user 
				$values_array[] = $status['id'];                            // id -> unique id of tweet 
				$values_array[] = $user['id'];                              // from_user_id
				$values_array[] = $status['lang'];                            // iso_language_code
				$values_array[] = mysql_real_escape_string($status['source']);// source
				$values_array[] = $user['profile_image_url'];               // profile_img_url
				$values_array[] = $geo['type'];                             // geo_type 
				$values_array[] = $geo['coordinates'][0];                   // geo_coordinates_0
				$values_array[] = $geo['coordinates'][1];                   // geo_coordinates_1
				$values_array[] = $status['created_at'];                    // created_at
				$values_array[] = strtotime($status['created_at']);         // time
				
				//JN : adding new attributes from JSON string
				$values_array[] = mysql_real_escape_string($user['name']);			// from_user_name
				$values_array[] = mysql_real_escape_string($user['location']);		// from_user_location
				$values_array[] = mysql_real_escape_string($user['url']);			// from_user_url
				$values_array[] = mysql_real_escape_string($user['description']);	// from_user_description
				$values_array[] = $user['created_at'];						// from_user_created_at
				
				if($user['verified'] == false){								// from_user_verified
					$values_array[] = 0;
				}
				else{
					$values_array[] = 1;
				}
				
				if($user['contributors_enabled'] == false){					// from_user_contributors_enabled
					$values_array[] = 0;
				}
				else{
					$values_array[] = 1;
				}
				
				if($status['truncated'] == false){							// truncated
					$values_array[] = 0;
				}
				else{
					$values_array[] = 1;
				}
				
				$values_array[] = $status['in_reply_to_status_id'];			// in_reply_to_status_id
				
				$entities = get_object_vars($status['entities']);
				
				//contributors
				if(array_key_exists('contributors', $entities)){
					$contributors = $entities['contributors'];

					$list_of_contributors = '';
					$i = 0;
					
					//for each contributor of this tweet
					//getting its id and screen_name
					for($i = 0; $i < count($contributors); $i++) {
						$list_of_contributors .= $contributors[$i]->id.":".$contributors[$i]->screen_name;
						
						if($i <> count($contributors) - 1){
							$list_of_contributors .= "\n";
						}
					}
					
					$values_array[] = $list_of_contributors;
				}
				else{
					$values_array[] = NULL;
				}
				
				//if the tweet is a RT
				if(array_key_exists('retweeted_status', $status)){
					$values_array[] = $status['retweeted_status']->id;		// initial_tweet_id
					$values_array[] = mysql_real_escape_string($status['retweeted_status']->text);	// initial_tweet_text
					$values_array[] = $status['retweeted_status']->user->id.":".$status['retweeted_status']->user->screen_name.":".mysql_real_escape_string($status['retweeted_status']->user->name);// initial_tweet_user
					$values_array[] = strtotime($status['retweeted_status']->created_at);
					
					//getting `entities` from $status['retweeted_status']
					//(avoid having original tweet user's name in user_mentions)
					$entities = get_object_vars($status['retweeted_status']->entities);
					//getting `user_mentions` from $entities
					$user_mentions = $entities['user_mentions'];
					
					//for each user mentionned in the ORIGINAL TWEET,
					//getting its id, screen_name (@xxx) and name
					$mentions = '';
					$i = 0;
					
					for($i = 0; $i < count($user_mentions); $i++) {
						$mentions .= $user_mentions[$i]->id . ":";
						$mentions .= $user_mentions[$i]->screen_name . ":";
						$mentions .= mysql_real_escape_string($user_mentions[$i]->name);
						
						if($i <> count($user_mentions) - 1){
							$mentions .= "\n";
						}
					}
					
					$values_array[] = $mentions;							// user_mentions
					
				}
				//if the tweet is not a RT
				else{
					$values_array[] = NULL;
					$values_array[] = NULL;
					$values_array[] = NULL;
					$values_array[] = 0;
					
					//getting `entities` from $status
					$entities = get_object_vars($status['entities']);
					//getting `user_mentions` from $entities
					$user_mentions = $entities['user_mentions'];
					
					//for each user mentionned in the tweet
					//getting its id, screen_name (@xxx) and name
					$mentions = '';
					$i = 0;
					
					for($i = 0; $i < count($user_mentions); $i++) {
						$mentions .= $user_mentions[$i]->id . ":";
						$mentions .= $user_mentions[$i]->screen_name . ":";
						$mentions .= mysql_real_escape_string($user_mentions[$i]->name);
						
						if($i <> count($user_mentions) - 1){
							$mentions .= "\n";
						}
					}
					
					$values_array[] = $mentions;							// user_mentions
				}
				
				//getting entities from tweet (not original tweet if RT)
				$entities = get_object_vars($status['entities']);
				
				//URLS
				$urls = $entities['urls'];

				$list_of_urls = '';
				$i = 0;
				
				//for each url used in the tweet
				//getting its expanded_url
				for($i = 0; $i < count($urls); $i++) {
					$list_of_urls .= mysql_real_escape_string($urls[$i]->expanded_url);
					
					if($i <> count($urls) - 1){
						$list_of_urls .= "\n";
					}
				}
				
				$values_array[] = $list_of_urls;							// urls
				
				//MEDIAS	
				if(array_key_exists('media', $entities)){
					$media = $entities['media'];
					
					$medias_urls = '';
					$i = 0;
					
					//for each media used in the tweet
					//getting its expanded_url
					for($i = 0; $i < count($media); $i++) {
						$medias_urls .= mysql_real_escape_string($media[$i]->expanded_url);
						
						if($i <> count($media) - 1){
							$medias_urls .= "\n";
						}
					}
					
					$values_array[] = $medias_urls;								// medias_urls
				}
				else{
					$values_array[] = NULL;
				}

				//HASHTAGS
				$hashtags = $entities['hashtags'];

				$list_of_hashtags = '';
				$i = 0;
				
				//for each hashtag used in the tweet
				//getting its text
				for($i = 0; $i < count($hashtags); $i++) {
					$list_of_hashtags .= $hashtags[$i]->text."";
					
					if($i <> count($hashtags) - 1){
						$list_of_hashtags .= "\n";
					}
				}
				
				$values_array[] = $list_of_hashtags;						// hashtags
				
				//SYMBOLS
				$symbols = $entities['symbols'];

				$list_of_symbols = '';
				$i = 0;
				
				//for each hashtag used in the tweet
				//getting its text
				for($i = 0; $i < count($symbols); $i++) {
					$list_of_symbols .= $symbols[$i]->text."";
					
					if($i <> count($symbols) - 1){
						$list_of_symbols .= "\n";
					}
				}
				
				$values_array[] = $list_of_symbols;							// symbols
				
				//JN
				
				$values = '';
				foreach ($values_array as $insert_value) {
					$values .= "'$insert_value',";
				}
				$values = substr($values,0,-1);
				
				$q = "insert into rawstream values($values)";
				$result = mysql_query($q, $db->connection);

				if(!$result)
				{
					echo "ERROR : INSERTION INTO RAWSTREAM FAILED :\n";
					echo mysql_error()."\n";
					echo "\tQuery : ".$q."\n";
					echo "\tTweet id : ".$value['id']."\n";
					echo "\tTime : ".time()."\n";
					echo "\n";
				}
				
			}
			else
			{
				echo "ERROR : STATUS ID == NULL :\n";
				print_r($status);
				echo "\n\n";
			}
		}
	}
 
	//JN : use type and account_id fields to create query
	public function checkFilterPredicates()
	{
		global $db;
		global $snf_vm_name;

		//test MySQL connection ONLY (if PG failed, continue to insert into rawstream)
		$r_test_mysql = mysql_query("select 1", $db->connection);
		
		if(!$r_test_mysql)
		{
			echo "ERROR : MYSQL DATABASE IS DOWN :\n";
			echo "\tQuery : "."select 1"."\n";
			echo "\tError : ".mysql_error()."\n";
			echo "\tTime : ".time()."\n";
			echo "\n";

			$die_message = $snf_vm_name." (".time().", ".date_format(date_create(), 'Y-m-d H:i:s').") - ";
			$die_message = $die_message . "yourtwapperkeeper_stream.php has been killed (MySQL DB is down)";
			mail("tee1@depinfo.u-bourgogne.fr", "YTK", $die_message);
			die($die_message);
		}
		else
		{
			echo "checkFilterPredicates\n";
			
			$q = "select id,keyword,type,account_id from archives";
			$r = mysql_query($q, $db->connection);
			
			$track = array();
			$follow = array();
			
			while ($row = mysql_fetch_assoc($r)) {
				//if the archive is about a keyword or hashtag
				if($row['type'] <> 'Account')
				{
					$track[] = $row['keyword'];
					$track_matrix['id'] = $row['keyword'];
				}
				//if the archive is about an account
				else
				{
					//to get tweets mentioning the user
					$track[] = $row['keyword'];
					$track_matrix['id'] = $row['keyword'];
					
					//to get tweets from the user
					if($row['account_id'] <> NULL)
					{
						$follow[] = $row['account_id'];
					}
				}
			}
			
			$this->setTrack($track);
			
			if(count($follow) > 0)
			{
				$this->setFollow($follow);
			}
			
			// update pid and last_ping in process table
			$pid = getmypid();
			mysql_query("update processes set last_ping = '".time()."' where pid = '$pid'", $db->connection);
			
			echo "update pid\n";
		}
	}
  
}

// Start streaming
$sc = new DynamicTrackConsumer($tk_oauth_token, $tk_oauth_token_secret, Phirehose::METHOD_FILTER);

try
{
	$sc->consume();
}catch(Exception $e)
{
	echo "ERROR : EXCEPTION FROM CONSUME :\n";
	echo "\tMessage : ".$e->getMessage()."\n";
	echo "\n";

	$die_message = $snf_vm_name." (".time().", ".date_format(date_create(), 'Y-m-d H:i:s').") - ";
	$die_message = $die_message . "yourtwapperkeeper_stream.php has died (exception from consume() : ".$e->getMessage().")";
	mail("tee1@depinfo.u-bourgogne.fr", "YTK", $die_message);
}


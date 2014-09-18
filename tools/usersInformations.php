<?php

require_once('/home/www/tee1/config.php');
require_once('/home/www/tee1/twitteroauth.php');

$connection = new TwitterOAuth($tk_oauth_consumer_key, $tk_oauth_consumer_secret, $tk_oauth_token, $tk_oauth_token_secret); 

//connection to MySQL to get accounts
$q = "select account_id from archives where type = 'Account'";
$r = mysql_query($q, $db->connection);

if(!$r)
{
	echo mysql_error();
}

$accounts = array();

while ($row = mysql_fetch_assoc($r))
{
	//without @
	$accounts[] = $row['account_id'];
}

//~ echo "COUNT : ".count($accounts)."\n";

//here, $accounts contains all accounts
if(count($accounts) > 0)
{
	while(count($accounts) > 100)
	{
		//get 100 accounts
		$subarray_accounts = array_slice($accounts, 0, 100);
		//query for 100 accounts
		$search = $connection->get('users/lookup', array('user_id' => implode(",", $subarray_accounts)));
		
		//for each user
		for($i=0;$i<count($search);$i++)
		{
			$searchresult = get_object_vars($search[$i]);
			//~ echo $searchresult['screen_name']."\n";
			//~ echo "\t".$searchresult['followers_count'];
			//~ echo "\t".$searchresult['friends_count']."\n";
			
			//insert into `users` the values
			$q = "insert into users(id, screen_name, last_update, followers_count, friends_count) values(".
					"'".$searchresult['id']."'".",".
					"'".$searchresult['screen_name']."'".",".
					//"'".time()."'".",".
					time().",".
					//"now(),".
					$searchresult['followers_count'].",".
					$searchresult['friends_count'].")";
			
			if(PGSQL_INSERT == true)
			{
				$r = pg_query($pgdb->connection, $q);
				
				if(!$r)
				{
					echo "PG ERROR\n";
				}
			}
		}
		
		//remove 100 first accounts (already processed)
		$accounts = array_slice($accounts, 100, count($accounts));
	}

	//here, count($accounts) <= 100 (1 query left)
	$search = $connection->get('users/lookup', array('user_id' => implode(",", $accounts)));
	
	for($i=0;$i<count($search);$i++)
	{
		$searchresult = get_object_vars($search[$i]);
		//~ echo $searchresult['screen_name']."\n";
		//~ echo "\t".$searchresult['followers_count'];
		//~ echo "\t".$searchresult['friends_count']."\n";
		
		$q = "insert into users(id, screen_name, last_update, followers_count, friends_count) values(".
					"'".$searchresult['id']."'".",".
					"'".$searchresult['screen_name']."'".",".
					"'".time()."'".",".
					$searchresult['followers_count'].",".
					$searchresult['friends_count'].")";
					
		if(PGSQL_INSERT == true)
		{
			$r = pg_query($pgdb->connection, $q);
			
			if(!$r)
			{
				echo "PG ERROR\n";
			}
		}
	}
}


?>

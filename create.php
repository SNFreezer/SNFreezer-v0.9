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

// validate information before creating
if (!(isset($_SESSION['access_token']['screen_name']))) {
	$_SESSION['notice'] = 'You must login to create an archive.';
	
	header('Location: index.php');
	die;
	}


if($_POST['listofkeywords']<>'')
{
	$connection = new TwitterOAuth($tk_oauth_consumer_key, $tk_oauth_consumer_secret, $tk_oauth_token, $tk_oauth_token_secret); 
	$connection->useragent = $youtwapperkeeper_useragent;
	
	$keywords = explode(",", $_POST['listofkeywords']);

	$accounts = array();

	foreach($keywords as $keyword => $data)
	{
		if($data <> '')
		{
			if(strpos($data, '@') === 0)
			{
				//echo "Account ".$data."\n";
				$accounts[] = substr($data, 1);
			}
			elseif(strpos($data, '#') === 0)
			{
				//echo "Hashtag ".$data."\n";
				$result = $tk->createArchive2($data,'Hashtag',NULL,'','',$_SESSION['access_token']['screen_name'],$_SESSION['access_token']['user_id']);
				//echo $result[0]."\n";
			}
			else
			{
				//echo "Keyword ".$data."\n";
				$result = $tk->createArchive2(mysql_real_escape_string($data),'Keyword',NULL,'','',$_SESSION['access_token']['screen_name'],$_SESSION['access_token']['user_id']);
				//echo $result[0]."\n";
			}
		}
	}

	if(count($accounts) > 0)
	{
		while(count($accounts) > 100)
		{
			$subarray_accounts = array_slice($accounts, 0, 100);
			
			$search = $connection->get('users/lookup', array('screen_name' => implode(",", $subarray_accounts)));
			
			for($i=0;$i<count($search);$i++)
			{
				$result = $tk->createArchive2('@'.$search[$i]->screen_name,'Account',$search[$i]->id,'','',$_SESSION['access_token']['screen_name'],$_SESSION['access_token']['user_id']);
				//echo $result[0]."\n";
			}
			
			$accounts = array_slice($accounts, 100, count($accounts));
		}

		$search = $connection->get('users/lookup', array('screen_name' => implode(",", $accounts)));
		
		for($i=0;$i<count($search);$i++)
		{
			$result = $tk->createArchive2('@'.$search[$i]->screen_name,'Account',$search[$i]->id,'','',$_SESSION['access_token']['screen_name'],$_SESSION['access_token']['user_id']);
			//echo $result[0]."\n";
		}
	}
}

$_SESSION['notice'] = $result[0];
header('Location: index.php');

/*
else
{
	// create and redirect
	$result = $tk->createArchive2($_POST['keyword'],'','',$_POST['description'],$_POST['tags'],$_SESSION['access_token']['screen_name'],$_SESSION['access_token']['user_id']);
	$_SESSION['notice'] = $result[0];
	header('Location: index.php');
}
*/
?>

<?php
/*
yourTwapperKeeper - Twitter Archiving Application - http://your.twapperkeeper.com
Copyright (c) 2010 John O'Brien III - http://www.linkedin.com/in/jobrieniii
Contributors : Eric Leclercq 2014 - Eric.Leclercq@u-bourgogne.fr 
               Jonathan Norblin 2014 - jonathan_norblin@etu.u-bourgogne.fr 

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

// Set Important / Load important
session_start();
require_once('config.php');
require_once('function.php');
require_once('twitteroauth.php'); 

// OAuth login check
if (empty($_SESSION['access_token']) || empty($_SESSION['access_token']['oauth_token']) || empty($_SESSION['access_token']['oauth_token_secret'])) {
$login_status = "<a href='./oauthlogin.php' ><img src='./resources/lighter.png'/></a>";  
$logged_in = FALSE;
} else {
$access_token = $_SESSION['access_token'];
$connection = new TwitterOAuth($tk_oauth_consumer_key, $tk_oauth_consumer_secret, $access_token['oauth_token'], $access_token['oauth_token_secret']);
$login_info = $connection->get('account/verify_credentials');
$login_status = "Hi ".$_SESSION['access_token']['screen_name'].", are you ready to archive?<br><a href='./clearsessions.php'>logout</a>";
$logged_in = TRUE;
}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
<title><?php echo $youtwapperkeeper_useragent; ?></title>
<link rel="icon" type="image/png" href="resources/SNFreezerIcon64.png" />
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<link href="resources/css/custom-theme/jquery-ui-1.8.4.custom.css" rel="stylesheet" type="text/css">
<link href="resources/css/yourtwapperkeeper.css?v=2" rel="stylesheet" type="text/css">
<script src="resources/js/jquery-1.4.2.min.js"></script>
<script src="resources/js/jquery-ui-1.8.4.custom.min.js"></script>
</head>

<body>

<div id='login'>
<p><?php echo $snf_app_name; ?></p>
<?php echo $login_status; ?>
<p><a href='index.php'><img src='resources/SNFreezerIcon64.png'/></a></p>
</div> <!-- end login div -->

<div id='header'>
<?php if ($logged_in) { ?>
<p><center>
<form action='create.php' method='post'>
<table class='none'>
<!--<td>Keyword or Hashtag: <input name='keyword'/></td>-->
<td>List of Keywords (comma-separated) :</td>
<td><textarea name="listofkeywords" cols="100" rows="7" value=""></textarea></td>
<!--<td>Description: <input name='description'/></td>
<td>Tags: <input name='tags'/></td>-->
<td><input type='submit' value ='Create Archives'/></td>
</table>
</form>
</center>
<br>
<?php
// allow start stop (only if in admin group)
if (in_array($_SESSION['access_token']['screen_name'],$admin_screen_name)) {
	$archiving_status = $tk->statusArchiving($archive_process_array);
	if ($archiving_status[0] == FALSE) {
	echo "<a href='startarchiving.php'><img src='./resources/play_24.png' alt='Start Archiving' title='Start Archiving'/></a>";
	} else {
	echo "<a href='stoparchiving.php'><img src='./resources/stop_24.png' alt='Stop Archving' title='Stop Archiving'/></a>";
	}
	echo "<b>".$archiving_status[1]."<b>";
	}
?>
</p>
<?php } ?>
</div> <!-- end header div -->

<div id='main'>
	<?php if (isset($_SESSION['notice'])) { ?>
	<div class='ui-widget'><div class='ui-state-highlight ui-corner-all' style='padding:5px; margin: 5px; width:750px; margin-left:auto; margin-right:auto; text-align:center'><span class='ui-icon ui-icon-info' style='float: left'></span><b><?php echo $_SESSION['notice']; ?></b></div></div>
	<?php
	 unset($_SESSION['notice']);
	 }?> 
	
<?php

// list table of archives
$archives = $tk->listArchive();

//JN : modifying display
echo "<table>";
echo "<tr><th>Archive ID</th><th>Keyword / Hashtag</th><th>Type</th><th>Description</th><th>Tags</th><th>Screen Name</th><th>Count</th><th>Create Time</th><th>STI</th><th>LTI</th><th>NSTI</th><th></th></tr>";
foreach ($archives['results'] as $value) {
        echo "<tr>";
        
        if ($_SESSION['localauth'] == 'LOCALAUTH:OK'){
			echo "<td>".$value['id']."</td>";
		}
		else{
			echo "<td></td>";
		}
		
		echo "<td>".$value['keyword']."</td><td>".$value['type']."</td><td>".$value['description']."</td><td>".$value['tags']."</td><td>".$value['screen_name']."</td><td>".$value['count']."</td><td>".date(DATE_RFC2822,$value['create_time'])."</td>";
		
		
		if ($_SESSION['localauth'] == 'LOCALAUTH:OK'){
			echo "<td>".$value['smallest_tweet_id']."</td>";
			echo "<td>".$value['largest_tweet_id']."</td>";
			echo "<td>".$value['no_smallest_tweet_id']."</td>";
		}
		else{
			echo "<td></td>";
			echo "<td></td>";
			echo "<td></td>";
		}
		
		echo "<td>";
		if ($_SESSION['localauth'] == 'LOCALAUTH:OK'){
			echo "<a href='archive.php?id=".$value['id']."' target='_blank' alt='View'><img src='./resources/binoculars_24.png' alt='View Archive' title='View Archive'/></a>"; 
		}
		
		if ($_SESSION['access_token']['screen_name'] == $value['screen_name']) {
			?>
			<script type="text/javascript">
				$(function() {  
					$("#deletedialog<?php echo $value['id']; ?>").dialog({
						autoOpen: false,  
						height: 150,
						width: 800,
						modal: true
						});
				
					$('#deletelink<?php echo $value['id']; ?>').click(function(){
						$('#deletedialog<?php echo $value['id']; ?>').dialog('open');
						return false;
						});
					
					$("#updatedialog<?php echo $value['id']; ?>").dialog({
						autoOpen: false,  
						height: 300,
						width: 300,
						modal: true
						});
				
					$('#updatelink<?php echo $value['id']; ?>').click(function(){
						$('#updatedialog<?php echo $value['id']; ?>').dialog('open');
						return false;
						});
						
						
					});          
			</script>
			
			<div id = 'deletedialog<?php echo $value['id']; ?>' title='Are you sure you want to delete <?php echo $value['keyword']; ?> archive?'>
			<br><br><center><form method='post' action='delete.php'><input type='hidden' name='id' value='<?php echo $value['id']; ?>'/><input type='submit' value='Yes'/></form></center>
			</div> 
			
			 <div id = 'updatedialog<?php echo $value['id']; ?>' title='Update <?php echo $value['keyword']; ?> archive'>
			<br><br><center><form method='post' action='update.php'>Description<br><input name='description' value='<?php echo $value['description']; ?>'/><br><br>Tags<br><input name='tags' value='<?php echo $value['tags']; ?>'/><input type='hidden' name='id' value='<?php echo $value['id']; ?>'/><br><br><p><input type='submit' value='Update'/></p></form></center>
			</div> 
			
			<?php
			echo "<a href='#' id='updatelink".$value['id']."'><img src='./resources/pencil_24.png' alt='Edit Archive' title='Edit Archive'/></a>";
			echo "  <a href='#' id='deletelink".$value['id']."'><img src='./resources/close_2_24.png' alt='Delete Archive' title='Delete Archive'/></a>";
		}
			
		echo "</td>";
		echo "</tr>";
	
}
echo "</table>";
?>

</div>


<div id='footer'>
<p> <?php echo $snf_vm_name; ?> - <?php echo "$snf_app_name - $yourtwapperkeeper_version"; ?> -- <a href="auth.html"> Show values </a></p>
</div>

</body>
</html>

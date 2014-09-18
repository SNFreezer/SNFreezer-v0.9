<?php

// Load important files
session_start();
require_once('config.php');
require_once('function.php');
require_once('twitteroauth.php');

$id = $_GET['id'];
$archiveInfo = $tk->listArchive($id);
if ($archiveInfo['count'] <> 1 || (!(isset($_GET['id'])))) {
	$_SESSION['notice'] = "Archive does not exist.";
	header('Location: index.php');
	}

// set link
$link = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
// set default limit
if ($_GET['l'] == '') {$limit = 10;} else {$limit = $_GET['l'];}
if ($_GET['o'] == '') {$orderby = 'd';} else {$orderby = $_GET['o'];} 

// set times
if ($_GET['sm'] <> '' && $_GET['sd'] <> '' && $_GET['sy'] <> '') {
    $start_time = strtotime($_GET['sm']."/".$_GET['sd']."/".$_GET['sy']);}
if ($_GET['em'] <> '' && $_GET['ed'] <> '' && $_GET['ey'] <> '') {
    $end_time = strtotime($_GET['em']."/".$_GET['ed']."/".$_GET['ey']);}
    
// Get tweets
if ($start_time <> '' || $end_time <> '') {
$archiveTweets = $tk->getTweets($_GET['id'],$start_time,$end_time,$limit,$orderby,$_GET['nort'],$_GET['from_user'],$_GET['text'],$_GET['lang'],$_GET['max_id'],$_GET['since_id'],$_GET['offset'],$_GET['lat'],$_GET['long'],$_GET['rad'],$_GET['debug']);
} else {
$archiveTweets = $tk->getTweets($_GET['id'],null,null,$limit,$orderby,$_GET['nort'],$_GET['from_user'],$_GET['text'],$_GET['lang'],$_GET['max_id'],$_GET['since_id'],$_GET['offset'],$_GET['lat'],$_GET['long'],$_GET['rad'],$_GET['debug']);
}

echo "<table border=1>";
echo "<tr>";
echo "<th>ARCHIVESOURCE</th>";
echo "<th>TEXT</th>";
echo "<th>TO_USER_ID</th>";
echo "<th>FROM_USER</th>";
echo "<th>ID</th>";
echo "<th>FROM_USER_ID</th>";
echo "<th>ISO_LANGUAGE_CODE</th>";
echo "<th>SOURCE</th>";
echo "<th>PROFILE_IMG_URL</th>";
echo "<th>GEO_TYPE</th>";
echo "<th>GEO_COORDINATES_0</th>";
echo "<th>GEO_COORDINATES_1</th>";
echo "<th>CREATED_AT</th>";
echo "<th>TIME</th>";

//JN : new columns
echo "<th>FROM_USER_NAME</th>";
echo "<th>FROM_USER_LOCATION</th>";
echo "<th>FROM_USER_URL</th>";
echo "<th>FROM_USER_DESCRIPTION</th>";
echo "<th>FROM_USER_CREATED_AT</th>";
echo "<th>FROM_USER_VERIFIED</th>";
echo "<th>FROM_USER_CONTRIBUTORS_ENABLED</th>";
echo "<th>TRUNCATED</th>";
echo "<th>IN_REPLY_TO_STATUS_ID</th>";
echo "<th>CONTRIBUTORS</th>";
echo "<th>INITIAL_TWEET_ID</th>";
echo "<th>INITIAL_TWEET_TEXT</th>";
echo "<th>INITIAL_TWEET_USER</th>";
echo "<th>INITIAL_TWEET_TIME</th>";
echo "<th>USER_MENTIONS</th>";
echo "<th>URLS</th>";
echo "<th>MEDIAS_URLS</th>";
echo "<th>HASHTAGS</th>";
echo "<th>SYMBOLS</th>";
//JN

echo "</tr>";

foreach ($archiveTweets as $key=>$value) {

	echo "<tr>";
	foreach ($value as $cols) {
		echo "<td>$cols</td>";}
	echo "</tr>";

} 

echo "</table>";
?>

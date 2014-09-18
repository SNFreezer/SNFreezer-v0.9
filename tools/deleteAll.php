<?php

session_start();
require_once('../config.php');
require_once('../function.php');
require_once('../twitteroauth.php'); 

// validate information before creating
if (!(isset($_SESSION['access_token']['screen_name']))) {
	$_SESSION['notice'] = 'You must login to create an archive.';
	header('Location: index.php');
	die;
}

$q_archives = "select id from archives";
$r_archives = mysql_query($q_archives, $db->connection);

while ($row_archives = mysql_fetch_assoc($r_archives))
{
	$result = $tk->deleteArchive($row_archives['id']);
	//~ echo $row_archives['id']." deleted\n";
}

?>



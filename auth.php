<?php 
session_start();
require_once('config.php');
require_once('function.php');

function protect($string){
    $string = trim(strip_tags(addslashes($string)));
    return $string;
}

if(isset($_REQUEST['login']) && isset($_REQUEST['password']))
 {
  echo test;
  $username = protect($_REQUEST['login']);
  $password = protect($_REQUEST['password']);
  echo  $username ;
  echo $password ;
  $query = "SELECT * FROM `localusers` WHERE `login` = '".$username."' AND `password` = '".md5($password)."'";
  echo  md5($password);
  $res = mysql_query($query, $db->connection);
  $num = mysql_num_rows($res);
  if($num == 0)
  {
   echo "Invalid password";
  }
  else
  {
   echo "ok";
	$_SESSION['localauth']="LOCALAUTH:OK";
	header("location:index.php");
  }
}
?>

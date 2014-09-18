<?php
/*
orignal contributor :
yourTwapperKeeper - Twitter Archiving Application - http://your.twapperkeeper.com
Copyright (c) 2010 John O'Brien III - http://www.linkedin.com/in/jobrieniii
contributors : Eric Leclercq - Eric.Leclercq@u-bourgogne.fr
               Jonathan Norblin - jonathan_norblin@etu.u-bourgogne.fr

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

// LOOK AT README FOR HOW TO CONFIGURE!!!!!!!!!!

/* Host Information */
$tk_your_url = "http://"; 								// make sure to include the trailing slash
$tk_your_dir = "/home/SNFreezer";  													// make sure to include the trailing slash
$youtwapperkeeper_useragent = "SN1";														// change to whatever you want!
$snfreezer_log = "/home/SNFreezer/log/snfreezer.log";									// change to logfile
$snf_vm_name = "";									// name of the VM
$snf_app_name = "SNFreezer";

/* Administrators - Twitter screen name(s) who can administer / start / stop archiving */
$admin_screen_name=array('tee_mars1'); 

/* Users - Twitter screen names that are allowed to use Your Twapper Keeper site - leaving commented means anyone can use site*/
 $auth_screen_name=array('JohnSmith','SallySue'); 



/* Your Twapper Keeper Twitter Account Information used to query for tweets (this is common for the site) */
$tk_twitter_username = '';               
$tk_twitter_password = '';
$tk_oauth_token = '';
$tk_oauth_token_secret = ''; 

/* Your Twapper Keeper Application Information - setup at http://dev.twitter.com/apps and copy in consumer key and secret */
$tk_oauth_consumer_key = '';
$tk_oauth_consumer_secret = '';

/* MySQL Database Connection Information */                                             
define("DB_SERVER", "localhost");										// change to your hostname
define("DB_USER", "");									// change to your db username
define("DB_PASS", "");												// change to your db password
define("DB_NAME", ""); 										// change to your db name
define("SNFREEZER_LOG", "/home/SNFreeze/log/snfreezer.log");

//JN
/* PostgreSQL Database Connection Information */
define("PGSQL_DB_SERVER", "192.168.0.1");
define("PGSQL_DB_PORT", "5432");
define("PGSQL_USER", "");
define("PGSQL_PASS", "");
define("PGSQL_NAME", "");
define("PGSQL_LOG", "/home/SNFreezer/log/pgsql.log");

//JN

//true only if we want to insert into PG DB
define("PGSQL_INSERT", true);

/* Don't mess with this unless you want to get your hands dirty */
$yourtwapperkeeper_version = "version 0.90a";
$archive_process_array = array('yourtwapperkeeper_crawl.php','yourtwapperkeeper_stream.php','yourtwapperkeeper_stream_process.php');
$twitter_api_sleep_min = 11;
$stream_process_stack_size = 500;
$php_mem_limit = "512M";
ini_set("memory_limit",$php_mem_limit);

class MySQLDB
{
   var $connection; 
   var $logfile;     

 function MySQLDB(){
      $this->connection = mysql_connect(DB_SERVER, DB_USER, DB_PASS) or die(mysql_error());
      mysql_select_db(DB_NAME, $this->connection) or die(mysql_error());
      mysql_set_charset('utf8mb4', $this->connection);
      $this->logfile = fopen(SNFREEZER_LOG,"a+");
   }

}
$db = new MySQLDB;

class PostgreSQLDB
{
	var $connection; 

	function PostgreSQLDB(){
		$connection_string = "host=".PGSQL_DB_SERVER." port=".PGSQL_DB_PORT." dbname=".PGSQL_NAME." user=".PGSQL_USER." password=".PGSQL_PASS."";
		$this->connection = pg_connect($connection_string) or die("Could not connect to PG");
	}

}

if(PGSQL_INSERT == true)
{
	$pgdb = new PostgreSQLDB;
}

?>

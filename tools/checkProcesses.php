<?php

//check number of processes ytk
exec("ps -U www-data -f | grep 'yourtwapperkeeper' | wc -l", $nb_processes);

//if 3 processes are running, ok
if($nb_processes[0] == 3)
{
	echo "".time().", ".date_format(date_create(), 'Y-m-d H:i:s')." : All processes are running\n";
}
//else, check what processes are running, and send a mail
else
{
	exec("ps -U www-data -f | grep 'yourtwapperkeeper'", $running_processes);
	
	$message = "One or more processes have died (".time().", ".date_format(date_create(), 'Y-m-d H:i:s').") - running processes : \n\n";
	
	for($i=0;$i<count($running_processes);$i++)
	{
		$message = $message . $running_processes[$i].",\n";
	}
	
	mail("tee1@depinfo.u-bourgogne.fr", "YTK", $message);
	echo "".time().", ".date_format(date_create(), 'Y-m-d H:i:s')." : ".$message."\n";
}

?>

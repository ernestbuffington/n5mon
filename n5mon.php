<?php
/* N5 NETWORKS SERVER MONITOR */
/* brian@n5net */
include_once("n5mon-config.php");		

array_shift($argv);
$action = $argv[0];
$id = $argv[1];
	echo "\n";			
	echo "N5 Networks System Monitor\n";			
	echo "Low overhead all purpose system monitor and maintenance tool\n";		
	echo "\n";		
	echo "2016, 2017 Brian Shaffer / N5 Networks\n";		
	echo "brian@n5net.com\n";		
	echo "Licensed under the GPL v2.0\n";		
	echo "\n";		

	if (!$action) 
	{

		echo "Command line options:\n";		
		echo "	php ./n5mon.php monitor - Runs all monitors\n";		
		echo "	php ./n5mon.php backup - Runs all backups\n";			
		echo "	php ./n5mon.php dbbackup - Backup and archive all databases\n";			
		echo "	php ./n5mon.php vscan - Perform Virus Scan\n";			
		echo "	php ./n5mon.php purge - Purge oldest backup files - saves the last 5\n";					
		echo "	php ./n5mon.php checkurl http://domain.com - check's to see the url is returning content and correct status codes\n";							
		echo "\n";
		echo "	php ./n5mon.php testemail - Sends a test message to all enabled emails in cfg file\n";							
		echo "\n";		
		echo "All options are stored in n5mon-config.php\n";		
		echo "\n";			
		echo "\n";			
		exit;
	}

if ($action == "checkurl")
{		
		$subject = '[SERVER MONITOR] ' . $server . ' ' . $id . ' FAILED MONITOR UPTIME CHECK!';
		$body = '';
		$siteisonline = 1;
		// Get the status code
		echo "Checking url " . $id . "\n";
		$stcode = get_url_status($id);
		if($stcode >= 400 && $stcode <= 599) {	
			echo "Status Code Check FAILED Status code = " . $ stcode . "\n";
			$body = "URL: " . $id . "\nStatus Code Check FAILED Status code = " . $ stcode . "\n";
			$siteisonline = 0;
		} else {
			echo "Status Code Check PASSED Status code = " . $ stcode . "\n";
			$siteisonline = 1;
		}
		
		// check for content at url
		$page = get_url_contents($id);
		if(!$page)
		{
			echo "NO CONTENT AT URL!\n";
			$body .= "URL: " . $id . "\nNo content found on page.\n";
			$siteisonline = 0;
		} else {
			echo "Content Check PASSED.\n";
		}	
		if(!$siteisonline)
		{
			if(!already_alerted($id,"1")) 
			{
				send_alert($subject,$body);
				record_alert($id,"1");
				if($GLOBALS['disk_helpdesk']) 
				{
					send_helpdesk($subject,$body);
				}
			}			
		} else {
			remove_alerted($id,"1");			
		}

}	
	
if($action == "testemail")
{
	$subject = "Test Message from N5MON on " . $GLOBALS['server'];	
	$body = "This is a test message.  If you got it, it works!";
	send_alert($subject, $body);
	send_helpdesk($subject, $body);
	echo "Sending test email...\n";
}


	
/* Remove Oldest Backup */
if($action == "purge")
{
		
		echo "- Removing mysql backups older than ". $GLOBALS['dbbackup_days'] . " days\n";
        echo "- Removing regular backups older than ". $GLOBALS['backup_days'] . " days\n";

		// Regular backups
		$xfile =  get_oldest_file($GLOBALS['backup_dir'],$GLOBALS['backup_days'] ); 
		echo "Oldest Is: ";
		echo $xfile;
		echo "\n";
		if ($xfile) { system("rm " . $GLOBALS['backup_dir']  . $xfile); }
		// Databases backups
		$xfile =  get_oldest_file($GLOBALS['dbbackup_dir'],$GLOBALS['dbbackup_days'] ); 
		echo "Oldest Is: ";
		echo $xfile;
		echo "\n";
		if ($xfile) { 		system("rm " . $GLOBALS['dbbackup_dir']  . $xfile);	}
}		
	
/* Backups */
if($action == "backup")
{			
	make_backup_dir();
	foreach($backup_dirs as $x => $x_value) 
	{
			echo "--Backup directory " . $x_value . "\n";	
			$today = date("Y-m-d");
			$server = $GLOBALS['server'];
			$server = str_replace(" ", "_" , $server);					
			$server = str_replace("'", "_" , $server);								
			$dest = $GLOBALS['backup_dir'];
			$outname = $server . "_" . $today . ".tar.gz";
			system("tar -cvzf " . $dest . $outname . " " . $x_value . "/*");	
	}       
}

/* Backup All Databases */
if($action == "dbbackup")
{			
	make_backup_dir();
	echo "--Backup All Databases\n";	
	dodumps($GLOBALS['db_host'], $GLOBALS['db_user'], $GLOBALS['db_pass']);
	echo "--Compressing backups\n";	
	zipdump("databases");
}

/* Scan for Viruses */
if($action == "vscan")
{			
	 // Update Virus Definitions
	echo "--Updating Virus definitions\n";	
	$cmdline = "freshclam";
    system($cmdline);
	
	foreach($scan_dirs as $x => $x_value) {
		echo "--Scanning directory " . $x_value . " for viruses\n";	
		virus_scan($x_value);
	}
}

/* Run Normal Monitors */
if($action == "monitor")
{	

	echo "----------------------------------------\n";
	echo "RUNNING ALL MONITORS\n";
	echo "----------------------------------------\n";
	echo "\n";
	
	// DISK MONITOR
	$bytes = disk_free_space("/");
	$si_prefix = array( 'B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB' );
    $base = 1024;
    $class = min((int)log($bytes , $base) , count($si_prefix) - 1);
    $gb_free = sprintf('%1.2f' , $bytes / pow($base,3));
	
	//echo $gb_free . " FREE\n\n";
	
	
	echo "--Checking if free disk space is at least " . $GLOBALS['disk_limit'] . "GB ...\n";
	if($GLOBALS['disk_limit']>$gb_free) {
		echo "---FAILED! Disk space check " . $gb_free . " GB available...\n";
		$server = $GLOBALS['server'];
		$subject = '[SERVER MONITOR] ' . $server . ' IS LOW ON DISK SPACE!';
		$body = $server . ' IS LOW ON DISK SPACE! There is currently ' . $gb_free . ' GB free space.';
		$body .= "\nGenerated by n5mon: https://github.com/q3shafe/n5mon by N5 Networks\n";
		if(!already_alerted("disk","1")) 
		{
			send_alert($subject,$body);
			record_alert("disk","1");
			if($GLOBALS['disk_helpdesk']) 
			{
				send_helpdesk($subject,$body);
			}
		}
		
	} else {
		echo "   PASSED! Disk space check, " . $gb_free . " GB available.\n";
		remove_alerted("disk","1");
	}
	echo "\n";
	// END DISK MONITOR

			
	foreach($processes as $x => $x_value) {
		echo "--Checking Process: " . $x . "\n";	
		exec("ps aux | grep " . $x_value, $pids);
		if(!$pids[2]) {
			// attempt to restart then check again
			echo "   FAILED! service " . $x . " (" . $x_value . ") is NOT running, Attempting restart.\n";
			write_service_log($x,$_value);
			exec($rprocesses[$x], $null);
			exec("sleep 10", $null);
			exec("ps aux | grep " . $x_value, $xpids);

			if(!$xpids[2]) {
				echo "   FAILED! service " . $x . " (" . $x_value . ") IS DOWN could not restart.\n";
				$server = $GLOBALS['server'];
				$subject = '[SERVER MONITOR] ' . $server . ' - ' . $x_value . ' IS DOWN!';
				$body = $server . ' Is reporting that service ' . $x . ' running ' . $x_value . ' IS NOT RUNNING!';
				$body .= "\nGenerated by n5mon: https://github.com/q3shafe/n5mon by N5 Networks\n";
				if(!already_alerted("process",$x)) 
				{
					send_alert($subject,$body);
					if($GLOBALS['process_helpdesk']) 
					{
						send_helpdesk($subject,$body);
					}
					record_alert("process",$x);
				}
						
			} else {
				echo "   OK! service " . $x . " (" . $x_value . ") HAS BEEN RESTARTED\n";
				$server = $GLOBALS['server'];
				$subject = '[SERVER MONITOR WARNING] ' . $server . ' - ' . $x_value . ' WAS DOWN!';
				$body = $server . ' Is reporting that service ' . $x . ' running ' . $x_value . ' was down, but I was able to restart it.  This may be worth investigating.';
				$body .= "\nGenerated by n5mon: https://github.com/q3shafe/n5mon by N5 Networks\n";
				
				send_alert($subject,$body);
				remove_alerted("process",$x);
			}
		} else {
			
			echo "   PASSED! service " . $x . " (" . $x_value . ") is running\n";
			remove_alerted("process",$x);
			
		}
		echo "\n";
		unset($pids);
		unset($xpids);
	}
	echo"\n";

	
	// LOAD Averages 1, 5 and 15
	$load = sys_getloadavg();
	$doload=1;
	unset($pids);
	exec("ps aux | grep gzip", $pids);
	if($pids[2]) {		
		$doload = 0;
	}
	unset($pids);
	exec("ps aux | grep clamscan", $pids);
	if($pids[2]) {		
		$doload = 0;
	}
	
	if($doload) {
	
		// 5 minute load
		echo "--Checking 1 Minute load average\n";	
		if($load[0] > $load_limits[0])
		{
					echo "   FAILED! 1 minute load average is above " . $load_limits[0] . ", currently . " . $load[0] . "\n";
					write_load_log($load[0],"1 minute load average");
					$server = $GLOBALS['server'];
					$subject = '[SERVER MONITOR] ' . $server . ' - Load Average Is High';
					$body = $server . " Load average test FAILED! 1 minute load average is above " . $load_limits[0] . ", currently . " . $load[0] . "\n\n";
					exec("ps aux | sort -nrk 3,3 | head -n 5", $topfive);
					$x=0;
					$y=count($topfive);
					while($x<$y) {
							$body .= $topfive[$x] . "\n";
							$x++;
					}
					$body .= "\nGenerated by n5mon: https://github.com/q3shafe/n5mon by N5 Networks\n";
					
					if(!already_alerted("load1","1")) 
					{
						send_alert($subject,$body);
						record_alert("load1","1");
					} 
		} else {
					echo "   PASSED\n";
					remove_alerted("load1","1");
		}
		echo"\n";
		echo "--Checking 5 Minute load average\n";	
		if($load[1] > $load_limits[1])
		{
					echo "   FAILED! 5 minute load average is above " . $load_limits[1] . ", currently . " . $load[1] . "\n";
					write_load_log($load[1],"5 minute load average");
					$server = $GLOBALS['server'];
					$subject = '[SERVER MONITOR] ' . $server . ' - Load Average Is High';
					$body = $server . " Load average test FAILED! 5 minute load average is above " . $load_limits[1] . ", currently . " . $load[1] . "\n\n";
					$x=0;
					$y=count($topfive);
					while($x<$y) {
							$body .= $topfive[$x] . "\n";
							$x++;
					}
					$body .= "\nGenerated by n5mon: https://github.com/q3shafe/n5mon by N5 Networks\n";
					
					if(!already_alerted("load5","1")) 
					{
						send_alert($subject,$body);
						record_alert("load5","1");
					} else {
						/*
						// r11?  run script for load averages?	- 	FIXME
						echo "   FAILED! service " . $x . " (" . $x_value . ") is NOT running, Attempting restart.\n";
						exec($rprocesses[$x], $null);
						*/
						
					}
		} else {
					echo "   PASSED\n";
					remove_alerted("load5","1");
		}
		echo"\n";
		echo "--Checking 15 Minute load average\n";	
		if($load[2] > $load_limits[2])
		{
					echo "   FAILED! 15 minute load average is above " . $load_limits[2] . ", currently . " . $load[2] . "\n";
					write_load_log($load[2],"15 minute load average");
					$server = $GLOBALS['server'];
					$subject = '[SERVER MONITOR] ' . $server . ' - Load Average Is High';
					$body = $server . " Load average test FAILED! 15 minute load average is above " . $load_limits[2] . ", currently . " . $load[2] . "\n\n";
					$x=0;
					$y=count($topfive);
					while($x<$y) {
							$body .= $topfive[$x] . "\n";
							$x++;
					}
					$body .= "\nGenerated by n5mon: https://github.com/q3shafe/n5mon by N5 Networks\n";
					
					if(!already_alerted("load15","1")) 
					{
						send_alert($subject,$body);
						record_alert("load15","1");
					}
		} else {
					echo "   PASSED\n";
					remove_alerted("load15","1");
		}
	} else {
		echo "--Skipping Load Check, Backup Or Virus Scan in Progress\n";	
	}
	
	
	/* ADD IN HTTP CHECKS */
	// -- FIXME
	
	echo "\n";
	echo "All tests have been completed.\n";
	
}	


function make_backup_dir()
{
	$dirname = $GLOBALS['backup_dir'];
    $filename = $dirname;
    if (!file_exists($filename)) {
       mkdir($dirname, 0777);
    }	
	$dirname = $GLOBALS['dbbackup_dir'];
    $filename = $dirname;
    if (!file_exists($filename)) {
       mkdir($dirname, 0777);
    }	

}

function zipdump ($servername) {
        $today = date("Y-m-d");		
        $outname = $GLOBALS['dbbackup_dir'] . $servername . "_" . $today . ".tar.gz";
		system("tar -cvzf " . $outname . " " . $GLOBALS['dbbackup_dir'] . "*.sql");
        system("rm " . $GLOBALS['dbbackup_dir'] . "*.sql");
}

function dodumps($db_host, $db_user, $db_pass) {

	$conn = mysqli_connect($db_host, $db_user, $db_pass, '');
	// Check connection
	
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error()); // TODO: log this or send alert 
	}
		echo 'connected to database';
		echo "\n";
		echo 'Getting Database List';
        $result = mysqli_query($conn, "show databases;")
        or die(mysql_error());
        //print_r($result);
        while($row = mysqli_fetch_assoc($result)) 
        {
                print 'Dumping ' . $row['Database'];
                print '
                ';
                $db = $row['Database'];
                $today = date("Y-m-d");
                $outname = $GLOBALS['dbbackup_dir'] . $db . "_" . $today . ".sql";
                system("mysqldump -P3308 -h" . $db_host ." -u" . $db_user . " -p" . $db_pass . " " . $db . " > " . $outname);
        }
}


function get_oldest_file($directory, $days) 
{ 
	$c=0;
    if ($handle = opendir($directory)) 
	{ 
        while (false !== ($file = readdir($handle))) 
		{ 
            $files[] = $file; 
        } 
        foreach ($files as $val) 
		{ 
			//	echo $val . "\n";
			if (is_file($directory.$val)) 
			{
				echo $val;
				echo "\n"; 
				$file_date[$val] = filemtime($directory.$val);
				$c++;
			} 
		}     
	} 
    if($c>$days) {
		closedir($handle);
		asort($file_date, SORT_NUMERIC); 
		reset($file_date); 
		$oldest = key($file_date); 
		return $oldest; 
	} else {
		return 0;
	}
	
} 

/// VIRUS Scans
function virus_scan($dir)
{
       $today = date("Y-m-d");

        // Run The Scan
        $cmdline = "clamscan -r -i " . $dir . " > /var/log/" . $today . "_virusscan.log";
        //echo $cmdline;
        //echo "\n";
        system($cmdline);

        $file = file_get_contents("/var/log/" . $today . "_virusscan.log");
        //echo $file;
        //echo "\n";
        //echo "\n";

        if (strpos($file,'FOUND') == true)
        {
			// Virus Found!
			$server = $GLOBALS['server'];
			$subject = "[SERVER MONITOR] " . $server . " - ACTION REQUIRED: A virus has been found on the server";
			$body = "A virus has been found on the server.\n\n";
			$body .= $file;
			$body .= "\n\nPlease take immediate action and remove these potential threats.";
			echo $body;
			echo "\n";
			send_alert($subject,$body);
			if($GLOBALS['virus_helpdesk']) 
			{
				send_helpdesk($subject,$body);
			}
			
        } else {
			$subject = "Virus Scan Completed - No Viruses Found";
			$body = "Virus Scan Completed - No Viruses Found";
			echo $subject . "\n";
        }
}	

function remove_alerted($type,$what)
{
	
	$path = $GLOBALS['n5mon_path'];
	$rline = $type . "," . $what . "\n";
	$file = file_get_contents($path . "/alerts.dat");
	$file = str_replace($rline, "", $file);			
	file_put_contents($path . "/alerts.dat", $file);		
}

function already_alerted($type,$what)
{
	$alerted = 0;
	$path = $GLOBALS['n5mon_path'];
	$fp = fopen($path . '/alerts.dat', 'r');
	while ( !feof($fp) )
	{
		$line = fgets($fp, 2048);
		$delimiter = ",";
		$data = str_getcsv($line, $delimiter);
		$xtype = $data[0];
		$xwhat = $data[1];
		if(($xwhat == $what) && ($xtype == $type)) { $alerted = 1; }
	}
	fclose($fp);
	return $alerted;
}

function record_alert($type,$what) 
{
	if(!already_alerted($type,$what)) 
	{
		 $path = $GLOBALS['n5mon_path'];
		 $file = $path = "/alerts.dat";
		 $line = $type . "," . $what . "\n";
		 file_put_contents($file, $line, FILE_APPEND);
	}
}

function write_load_log($load,$desc) 
{
		 $file = $GLOBALS['load_log'];
		 $time = date("h:i:sa");
		 $today = date("Y-m-d");
		 $line = $desc . "," . $load . "," . $time . "," . $today . "\n";
		 if($file) 
		 {
			file_put_contents($file, $line, FILE_APPEND);
		 }
}
	
function write_service_log($service,$desc) 
{
		 $file = $GLOBALS['service_log'];
		 $time = date("h:i:sa");
		 $today = date("Y-m-d");
		 $line = $desc . "," . $service . "," . $time . "," . $today . "\n";
		 if($file) 
		 {
			file_put_contents($file, $line, FILE_APPEND);
		 }
}
function send_alert($subject, $body)
{
            echo "   ACTION: Sending Alert to " . $GLOBALS['alert_email'] . " - " . $subject . "\n"; 
			echo $body;
			$headers = "From: " . $GLOBALS['from_email'] . "\r\n";
			mail($GLOBALS['alert_email'],$subject,$body,$headers);	
			mail($GLOBALS['sms_email'],$subject,$body,$headers);	
			// Send sms
			
}	

function send_helpdesk($subject, $body)
{
            echo "   ACTION: HELPDESK Alert  to " . $GLOBALS['helpdesk_email'] . " - " . $subject . "\n"; 
			echo $body;
			$headers = "From: " . $GLOBALS['from_email'] . "\r\n";
			mail($GLOBALS['helpdesk_email'],$subject,$body,$headers);	
		
}	

/*
		http status stuff // future
*/

/*
  Returns the contents of any given url
*/
function get_url_contents($url){
        $crl = curl_init();
        $timeout = 15;
        curl_setopt ($crl, CURLOPT_URL,$url);
        curl_setopt ($crl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
        $ret = curl_exec($crl);
		$http_status = curl_getinfo($crl, CURLINFO_HTTP_CODE);				
        curl_close($crl);
		echo "STATUS: " . $http_status . "\n";
        return $ret;
}

/*
  Get's the http status code of any url
  returns 404,500 etc.
*/

function get_url_status($url){
        $crl = curl_init();
        $timeout = 15;
        curl_setopt ($crl, CURLOPT_URL,$url);
        curl_setopt ($crl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
        $ret = curl_exec($crl);
		$http_status = curl_getinfo($crl, CURLINFO_HTTP_CODE);				
        curl_close($crl);
		echo "STATUS: " . $http_status . "\n";
        return $http_status;
}


/*
 Checks if a url loads any headers
*/

function url_exists($url){
     if ((strpos($url, "http")) === false) $url = "http://" . $url;
     if (is_array(@get_headers($url)))
          return true;
     else
          return false;
}


?>
<?php
/* N5 NETWORKS SIMPLE HEALTH CHECK */
/* brian@n5net */
include_once("n5mon-config.php");		

error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE); // Don't show warnings

array_shift($argv);
$action = $argv[0];
$id = $argv[1];

	echo "\n";			
	echo "-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=--=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-\n";
	echo "N5 Networks System Health Check\n";			
	echo "2016, 2017 Brian Shaffer / N5 Networks\n";		
	echo "brian@n5net.com\n";		
	echo "http://dev.n5net.com/ \n";
	echo "\n";			
	echo "-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=--=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-\n";
	echo "[NOTICE] Starting.\n";
	

	
$path = $GLOBALS['n5mon_path'];
$totalfailures = 0;

/* Run Normal Monitors */

	echo "\n";
	echo "[ACTION] RUNNING HEALTH CHECK\n";	
	echo "\n";

	// Get Uptime
	$str   = @file_get_contents('/proc/uptime');
	$num   = floatval($str);
	$secs  = fmod($num, 60); $num = (int)($num / 60);
	$mins  = $num % 60;      $num = (int)($num / 60);
	$hours = $num % 24;      $num = (int)($num / 24);
	$days  = $num;
	
	echo "[NOTICE] System has been up for " . $days . " days, " . $hours . " hours, " . $mins . " minutes and " . $secs . " seconds. \n";
	echo "\n";
	
	
	// System Memory
	echo "[ACTION] Checking System Memory \n";
	 $fh = fopen('/proc/meminfo','r');
	$mem = 0;
	while ($line = fgets($fh)) {
		
		echo "[RESULT] " . $line . "";
		//break;
		
	}
	fclose($fh);
	echo "\n";
	
	// ps -eo pmem,pcpu,pid,user,args | sort -k 1 -r | head -6|sed 's/$/\n/'
	echo "[ACTION] Showing processes using the most memory. \n";
	exec("ps -eo pmem,pcpu,pid,user,args | sort -k 1 -r | head -6", $topfive);
					$x=0;
					$y=count($topfive);
					while($x<$y) {
							echo "[RESULT] " . $topfive[$x] . "\n";
							$x++;
					}
	
	// DISK MONITOR
	$bytes = disk_free_space("/");
	$si_prefix = array( 'B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB' );
    $base = 1024;
    $class = min((int)log($bytes , $base) , count($si_prefix) - 1);
    $gb_free = sprintf('%1.2f' , $bytes / pow($base,3));
	
	//echo $gb_free . " FREE\n\n";
	echo "\n";
	
	echo "[ACTION] Checking if free disk space is at least " . $GLOBALS['disk_limit'] . "GB ...\n";
	if($GLOBALS['disk_limit']>$gb_free) {
		echo "[RESULT] FAILED! Disk space check " . $gb_free . " GB available...\n";
		$totalfailures++;
		$server = $GLOBALS['server'];
		$subject = '[SERVER MONITOR] ' . $server . ' IS LOW ON DISK SPACE!';
		$body = $server . ' IS LOW ON DISK SPACE! There is currently ' . $gb_free . ' GB free space.';
		$body .= "\nGenerated by n5mon: https://github.com/q3shafe/n5mon by N5 Networks\n";
		
	} else {
		echo "[RESULT] PASSED! Disk space check, " . $gb_free . " GB available.\n";
	}
	echo "\n";
	// END DISK MONITOR

			
	foreach($processes as $x => $x_value) {
		echo "[ACTION] Checking Process: " . $x . "\n";	
		exec("ps aux | grep " . $x_value, $pids);
		if(!$pids[2]) {
			// attempt to restart then check again
			echo "[RESULT] FAILED! service " . $x . " (" . $x_value . ") is NOT running.\n";
			$totalfailures++;

		} else {
			
			echo "[RESULT] PASSED! service " . $x . " (" . $x_value . ") is running\n";
			
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
		echo "[ACTION] Checking 1 Minute load average\n";	
		if($load[0] > $load_limits[0])
		{
					$totalfailures++;
					echo "[RESULT] FAILED! 1 minute load average is above " . $load_limits[0] . ", currently . " . $load[0] . "\n";
					exec("ps aux | sort -nrk 3,3 | head -n 5", $topfive);
					$x=0;
					$y=count($topfive);
					while($x<$y) {
							//echo $topfive[$x] . "\n";
							$x++;
					}
					
					
		} else {
					echo "[RESULT] PASSED 1 MINUTE LOAD IS " . $load[0] . "\n";

		}
		echo"\n";
		echo "[ACTION] Checking 5 Minute load average\n";	
		if($load[1] > $load_limits[1])
		{
					echo "[RESULT] FAILED! 5 minute load average is above " . $load_limits[1] . ", currently . " . $load[1] . "\n";
					$totalfailures++;
					$x=0;
					$y=count($topfive);
					while($x<$y) {
							$body .= $topfive[$x] . "\n";
							$x++;
					}
					
		} else {
					echo "[RESULT] PASSED 5 MINUTE LOAD IS " . $load[1] . "\n";
					
		}
		echo"\n";
		echo "[ACTION] Checking 15 Minute load average\n";	
		if($load[2] > $load_limits[2])
		{
					echo "[RESULT] FAILED! 15 minute load average is above " . $load_limits[2] . ", currently . " . $load[2] . "\n";
					$totalfailures++;
					$x=0;
					$y=count($topfive);
					while($x<$y) {
							$body .= $topfive[$x] . "\n";
							$x++;
					}

					

		} else {
					echo "[RESULT] PASSED 15 MINUTE LOAD IS " . $load[2] . "\n";
					
		}
	} else {
		echo "[NOTICE] !! Skipping Load Check, Backup Or Virus Scan in Progress !!\n";	
	}
	
	echo "\n";
	
	// ps -eo pmem,pcpu,pid,user,args | sort -k 1 -r | head -6|sed 's/$/\n/'
	echo "[ACTION] Showing processes using the most CPU. \n";
	exec("ps -eo pcpu,pid,user,args | sort -k 1 -r | head -6", $xtopfive);
					$x=0;
					$y=count($topfive);
					while($x<$y) {
							echo "[RESULT] " . $xtopfive[$x] . "\n";
							$x++;
					}
	
if($action == "dovirus")
{	
	echo "\n";
	echo "[NOTICE] Preparing for quick virus scan.  This may take some time. \n";
	echo "\n";


/* Scan for Viruses */


	 // Update Virus Definitions
	echo "[ACTION] Updating Virus definitions\n";	
	$cmdline = "freshclam";
    system($cmdline);
	
	foreach($scan_dirs as $x => $x_value) {
		echo "[ACTION] Scanning directory " . $x_value . " for viruses\n";	
		virus_scan($x_value);
	}
}


	echo "\n\n";
	echo "[NOTICE] **** All tests have been completed. ****\n\n";

	if($totalfailures)
	{
		echo "[ALERT!] PROBLEMS FOUND! THERE WHERE " . $totalfailures . " ISSUES FOUND.\n";
	} else {
		echo "[NOTICE] NO PROBLEMS FOUND! \n";
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
			$totalfailures++;
			$server = $GLOBALS['server'];
			$body = "A virus has been found on the server.\n\n";
			$body .= $file;
			echo "[RESULT] " . $body;
			//echo "\n";
			
        } else {
			$subject = "Virus Scan Completed - No Viruses Found";
			echo "[RESULT] " .$subject . "\n";
        }
}	


?>
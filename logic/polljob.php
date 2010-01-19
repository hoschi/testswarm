<?php

	$title = "poll job";

	if ( $_REQUEST['state'] == "polljob" ) {
		$username = preg_replace("/[^a-zA-Z0-9_ -]/", "", $_REQUEST['user']);
		$auth = preg_replace("/[^a-z0-9]/", "", $_REQUEST['auth']);

		$result = mysql_queryf("SELECT id FROM users WHERE name=%s AND auth=%s;", $username, $auth);

		if ( $row = mysql_fetch_array($result) ) {
			$user_id = intval($row[0]);

		# TODO: Improve error message quality.
		} else {
			echo "Incorrect username or auth token.";
			exit();	
		}

		$jobId = preg_replace("/[^0-9]/", "", $_REQUEST['job_id']);
		echo "job id = $jobId<br/>";

		// save last 4 run statistics
		$lastRun[0] = -2;
		$lastRun[1] = -1;
		$lastRun[2] = -1;
		$lastRun[3] = -1;

		$running = true;
		while($running)
		{
			// copy last runs
			$lastRun[3] = $lastRun[2];
			$lastRun[2] = $lastRun[1];
			$lastRun[1] = $lastRun[0];

			// is a test running?
			$testInProgress = true;
			while($testInProgress)
			{
				$result = mysql_queryf("SELECT COUNT( run_useragent.status ) FROM runs INNER JOIN run_useragent ON run_useragent.run_id = runs.id WHERE job_id=$jobId AND run_useragent.status =1");

				if ( $row = mysql_fetch_array($result) ) {
					if($row[0] > 0)
						sleep(15);
					else
						$testInProgress = false;
				}
				else
					$testInProgress = false;
			}

			// poll for job runs and show if there are new results. with "curl -m XX" you 
			// can set the timeout for clientscripts
			$result = mysql_queryf("SELECT COUNT( run_useragent.status ) FROM runs INNER JOIN run_useragent ON run_useragent.run_id = runs.id WHERE job_id=$jobId AND run_useragent.status =2");

			if ( $row = mysql_fetch_array($result) ) {
				$lastRun[0] = intval($row[0]);
			}
			else
			{
				$lastRun[0] = -1;
			}

			// last 4 runs nothing happend -> nothing changing any more, stopp 
			// and print the result
			if(    $lastRun[0] == $lastRun[1] 
				&& $lastRun[1] == $lastRun[2] 
				&& $lastRun[2] == $lastRun[3])
			{
				$running = false;
			}
			else
			{
				sleep(30);
			}
		}

		echo "last count of successfull tests: ".$lastRun[0]."<br/>";
		echo "job finished";

		exit();	
	}
?>

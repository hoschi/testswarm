<?php
$currentDate = date('Y-m-d_H:i:s');
$testrunner = 'doh';
$jobId = preg_replace("/[^0-9]/", "", $_REQUEST['job_id']);
if ($_REQUEST['packagename'] != "")
	$packageName = $_REQUEST['packagename'];
else
	$packageName = "testswarm";
if ($_REQUEST['outputdir'] != "")
	$outputDir = $_REQUEST['outputdir'];
else
	$outputDir = "temp";

echo 'writing job data ...<br/>';
echo "job id = $jobId<br/>";
echo "packagename = $packageName<br/>";
echo "output dir = $outputDir<br/>";


$names= array();
// generate one testsuite for each run
$result = mysql_queryf("SELECT name,id FROM runs WHERE job_id = $jobId;");
while ( $run = mysql_fetch_array($result) ) 
{
	$names[$packageName.'.'.$run['name']]['runId'] = $run['id'];
}

foreach ($names	as $name => $value)
{
	$runId = $value['runId'];
	$xmlstr = "<?xml version='1.0' ?>\n<testsuite></testsuite>";
	// get one testsuite for each useragent (mozilla 3.5, opera 10, ...)
	$result = mysql_queryf("SELECT run_id,useragent_id,name,os FROM run_useragent LEFT OUTER JOIN useragents ON run_useragent.useragent_id = useragents.id WHERE run_id=$runId AND status=2");
	while ( $runAtAgend = mysql_fetch_array($result) ) 
	{
		$xml = new SimpleXMLExtended($xmlstr);
		$testsuiteName = $name.'.'.str_replace('.', '-', $runAtAgend['name']." on ".$runAtAgend['os']);
		$xml->addAttribute('name', $testsuiteName);
		$value['useragents'][$runAtAgend['useragent_id']]['xml'] = $xml;
		$value['useragents'][$runAtAgend['useragent_id']]['name'] = $testsuiteName;
	}

	// create on test foreach clientrun of user agent
	foreach ($value['useragents'] as $userAgentId => $useragent)
	{
		$xml = $useragent['xml'];
		$errors = 0;
		$tests = 0;
		$failures = 0;
		$sysout = '';
		$result = mysql_queryf("SELECT fail,error,total,results,ip,useragent FROM run_client INNER JOIN clients ON run_client.client_id = clients.id WHERE useragent_id=$userAgentId AND run_id=$runId");
		while ( $runAtClient= mysql_fetch_array($result) ) 
		{
			$clientName = $runAtClient['ip']." with ".$runAtClient['useragent'];

			$badTests = $runAtClient['error'] + $runAtClient['fail'];
			for($i = 0; $i < $runAtClient['total']; ++$i)
			{
				$childXml = $xml->addChild('testcase');
				$childXml->addAttribute('name', $clientName);
				if($badTests > 0)
				{
					$childXml->addAttribute('fail', 'true');
					--$badTests;
				}
			}
			$errors += $runAtClient['error'];
			$tests += $runAtClient['total'];
			$failures += $runAtClient['fail'];

			// format results
			$formattedResults = str_replace('<div>', '', gzuncompress($runAtClient['results']));
			$formattedResults = str_replace('<DIV>', '', $formattedResults);
			$formattedResults = str_replace('<pre>', '', $formattedResults);
			$formattedResults = str_replace('</pre>', '', $formattedResults);
			$formattedResults = str_replace('</div>', "\n", $formattedResults);
			$formattedResults = str_replace('</DIV>', "\n", $formattedResults);
			$formattedResults = str_replace('&nbsp;', " ", $formattedResults);
			$formattedResults = str_replace('', " ", $formattedResults);

			$sysout .= $clientName."\n".$formattedResults."\n\n";
		}

		$xml->addAttribute('errors', $errors);
		$xml->addAttribute('tests', $tests);
		$xml->addAttribute('failures', $failures);
		$xml->addChild('system-out')->addCData($sysout);

		$userAgentName = $useragent['name'];

		echo '<strong>'.$userAgentName.'</strong> finished';
		echo "<pre>".htmlentities(formatXmlString($xml->asXML()))."</pre>";
		
		if ( $_REQUEST['output'] == "file" )
		{
			$fileName = "$outputDir/$packageName-job$jobId-${userAgentName}_$currentDate.xml";
			$fileName = preg_replace('/\s+/', '_', $fileName);
			$xml->asXML($fileName);
			echo " and file writen to ".$fileName;
			//$root->asXML("/tmp/testswarm/job-$jobId-".date('Ymd-His').".xml");
		}
		echo "<br/>";
	}

}
echo 'finished';

//==============================================================================
// helper
//==============================================================================
class SimpleXMLExtended extends SimpleXMLElement
{
  public function addCData($cdata_text)
  {
    $node= dom_import_simplexml($this);
    $no = $node->ownerDocument;
    $node->appendChild($no->createCDATASection($cdata_text));
  }
} 
function formatXmlString($xml) {  
  
  // add marker linefeeds to aid the pretty-tokeniser (adds a linefeed between all tag-end boundaries)
  $xml = preg_replace('/(>)(<)(\/*)/', "$1\n$2$3", $xml);
  
  // now indent the tags
  $token      = strtok($xml, "\n");
  $result     = ''; // holds formatted version as it is built
  $pad        = 0; // initial indent
  $matches    = array(); // returns from preg_matches()
  
  // scan each line and adjust indent based on opening/closing tags
  while ($token !== false) : 
  
    // test for the various tag states
    
    // 1. open and closing tags on same line - no change
    if (preg_match('/.+<\/\w[^>]*>$/', $token, $matches)) : 
      $indent=0;
    // 2. closing tag - outdent now
    elseif (preg_match('/^<\/\w/', $token, $matches)) :
      $pad--;
    // 3. opening tag - don't pad this one, only subsequent tags
    elseif (preg_match('/^<\w[^>]*[^\/]>.*$/', $token, $matches)) :
      $indent=1;
    // 4. no indentation needed
    else :
      $indent = 0; 
    endif;
    
    // pad the line with the required number of leading spaces
    $line    = str_pad($token, strlen($token)+$pad, ' ', STR_PAD_LEFT);
    $result .= $line . "\n"; // add to the cumulative result, with linefeed
    $token   = strtok("\n"); // get the next token
    $pad    += $indent; // update the pad size for subsequent lines    
  endwhile; 
  
  return $result;
}
 
?>

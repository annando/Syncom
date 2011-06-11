<?php
function getexpire($newsgroup)
{
	$expirecfg = file_get_contents("/etc/news/expire.ctl");
	//die($expirecfg);
	preg_match_all('/(.*):[MUA]:(?:\d+|never):(.*):(?:\d+|never)/i', $expirecfg, $lines);
	//print_r($lines);
	$expiretime = 0;
	foreach($lines[1] as $index => $grouppattern) {
		$testpattern = $grouppattern;
		$testgroup = $newsgroup;
		//echo $index."-".$lines[2][$index]."-".$grouppattern."\n";
		if (substr($grouppattern, -1) == "*") {
			$testpattern = substr($grouppattern, 0, -1);
			$testgroup = substr($testgroup, 0, strlen($testpattern));
		}
		if ($testpattern == $testgroup)
			$expiretime = $lines[2][$index];
	}
	return($expiretime);
}

echo "\n".getexpire('pirates.de.public.test');
?>

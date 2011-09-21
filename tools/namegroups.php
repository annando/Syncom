<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'namegroups.php');
define("IN_SYNCOM", 1);

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../../global.php";

require MYBB_ROOT.'/syncom/config.php';

include('spyc.php');

require_once "../mybbapi.php";

$foruminfo = array();

function search_ng($item, $key)
{
	global $db, $foruminfo;

	if (is_array($item) and array_key_exists('nntp', $item)) {
		echo $item['nntp']."\n";
		$query = $db->simple_select("forums", "syncom_newsgroup, fid, rules, modposts", "syncom_newsgroup='".$item['nntp']."'");

		if ($row = $db->fetch_array($query)) {
			$desc = $row['rules'];

			$list = explode('@', $item['from']);

			if ($list[1] == 'lists.piratenpartei.de')
				$maillink = "<a href='https://service.piratenpartei.de/mailman/listinfo/".$list[0]."'>".$item['from']."</a>";
			else if ($list[1] == 'lists.piratenpartei-bayern.de')
				$maillink = "<a href='https://lists.piratenpartei-bayern.de/mailman/listinfo/".$list[0]."'>".$item['from']."</a>";
			else if ($list[1] == 'piratenpartei-hessen.de')
				$maillink = "<a href='http://lists.piratenpartei-hessen.de/mailman/listinfo/".$list[0]."'>".$item['from']."</a>";
			else if ($list[1] == 'lists.piraten-nds.de')
				$maillink = "<a href='http://lists.piraten-nds.de/mailman/listinfo/".$list[0]."'>".$item['from']."</a>";
			else if ($list[1] == 'lists.piraten-sachsen.de')
				$maillink = "<a href='https://lists.piraten-sachsen.de/cgi-bin/mailman/listinfo/".$list[0]."'>".$item['from']."</a>";
			else if ($list[1] == 'saar.piratenpartei.de')
				$maillink = "<a href='https://intern.saar.piratenpartei.de/mailman/listinfo/".$list[0]."'>".$item['from']."</a>";
			else
				$maillink = $item['from'];

			$desc .= "\r\nMailingliste: ".$maillink;

			if ($row['modposts'])
				$desc .= "\nDieser Bereich ist moderiert. Schreibberechtigt sind nur Mitglieder der angeschlossenen Mailingliste.";


			$foruminfo[$row['fid']]['maillink'] = $maillink;
			$foruminfo[$row['fid']]['mail'] = $item['from'];

			//echo $desc."\r\n\r\n";

			$db->update_query("forums", array('rules' => $db->escape_string($desc),
							'rulestitle' => 'Foreninformation',
							'rulestype' => 1),
						"fid=".$row['fid']);
		}

	}
}

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

$api = new mybbapi;

$query = $db->simple_select("forums", "syncom_newsgroup, fid", "syncom_newsgroup!='' order by syncom_newsgroup");

while ($row = $db->fetch_array($query)) {

	$desc = "<a href='http://wiki.piratenpartei.de/Syncom/Forum'>Informationen &uuml;ber die Synchronisation</a>";

	$fpermissions = forum_permissions($row['fid'], 0);
	//print_r($fpermissions);

	if ($fpermissions['canviewthreads'])
		$desc .= "\nDie Beiträge sind auch ohne Anmeldung sichtbar. ";
	else
		$desc .= "\nDie Beiträge sind erst nach Anmeldung sichtbar. ";

	$foruminfo[$row['fid']]['open'] = $fpermissions['canview'];
	$foruminfo[$row['fid']]['public'] = $fpermissions['canviewthreads'];

	$expiretime = getexpire($row['syncom_newsgroup']);

	$foruminfo[$row['fid']]['expire'] = $expiretime;

	if ($expiretime == "never")
		$desc .= "Sie werden dauerhaft vorgehalten";
	else
		$desc .= "Sie werden nach etwa ".$expiretime." Tagen automatisiert entfernt";

	$desc .= " und stehen ebenfalls auf den folgenden Medien zur Verfügung:";
	$desc .= "\nNewsgroup: <a href='news://news.piratenpartei.de/".$row['syncom_newsgroup']."'>".$row['syncom_newsgroup']."</a>";

	$foruminfo[$row['fid']]['newsgroup'] = $row['syncom_newsgroup'];

	//echo $desc."\n\n";

	$db->update_query("forums", array('rules' => $db->escape_string($desc),
					'rulestitle' => 'Foreninformation',
					'rulestype' => 1),
			"fid=".$row['fid']);

}

$yaml = Spyc::YAMLLoad('/usr/local/etc/synfu.conf');

array_walk($yaml, 'search_ng');

$info  = '{| class="wikitable sortable"';
$info .= "\n! Forum !! Newsgroup !! Mailingliste !! Öffentlich !! Haltezeit";
//$info .= "\n|-";

//print_r($foruminfo);
foreach($foruminfo as $id => $forum) {
	if ($forum['open']) {
		$info .= "\n|-";
		$info .= "\n| [https://news.piratenpartei.de/forumdisplay.php?fid=".$id." Forum]";
		$info .= "\n| ".$forum['newsgroup'];
		$info .= "\n| ".$forum['mail'];
		if ($forum['public'])
			$info .= "\n| X";
		else
			$info .= "\n|  ";
		$info .= "\n| ".$forum['expire'];
	}
}
$info .= "\n|}";
file_put_contents('/srv/www/news01/syncom/tools/groupinfo.txt', utf8_decode($info));
//echo $info;
?>

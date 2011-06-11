<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'fetchnews.php');
define("IN_SYNCOM", 1);

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../global.php";

require MYBB_ROOT.'/syncom/config.php';

require_once "mybbapi.php";

global $db, $syncom;

$query = $db->simple_select("alt_forums", "*", "");

while ($forum = $db->fetch_array($query)) {
	echo $forum['forum_name']."\r\n";
	$data = array("fid" => $forum['forum_id'],
			"name" => $forum['forum_name'],
			"description" => $forum['forum_desc'],
			"pid" => $forum['parent_id'],
			"syncom_newsgroup" => $forum['newsgroup'],
			"active" => 1,
			"open" => 1,
			"allowmycode" => 1,
			"allowsmilies" => 1,
			"allowimgcode" => 1,
			"allowvideocode" => 1,
			"allowpicons" => 1,
			"allowtratings" => 1,
			"status" => 1,
			"usepostcounts" => 1,
			"showinjump" => 1,
			);

	if ($forum['forum_type'] == 0)
		$data['type'] = 'c';
	else
		$data['type'] = 'f';

	//print_r($data);

	$db->insert_query("forums", $data);

}

$query = $db->simple_select("forums", "*", "parentlist = ''");

while ($forum = $db->fetch_array($query)) {
	echo $forum['name'];

	$parent = '';

	//if ($forum['pid'] != 0)
	//	$parent = $forum['pid'].',';

	$parent .= $forum['fid'];

	$pid = $forum['pid'];
	while ($pid != 0) {
		$query2 = $db->simple_select("forums", "*", "fid = ".$pid);
		if ($forum2 = $db->fetch_array($query2)) {
			$parent = $forum2['fid'].','.$parent;
			$pid = $forum2['pid'];
		}
	}
	echo " - ".$parent."\n";
	$db->update_query("forums", array('parentlist'=>$parent), "fid=".$forum['fid']);

}

?>

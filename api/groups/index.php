<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'index.php');
define("IN_SYNCOM", 1);

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../../../global.php";

require MYBB_ROOT.'/syncom/config.php';

require_once MYBB_ROOT."/syncom/mybbapi.php";

$api = new mybbapi;

$username = $_REQUEST['username'];
$username = str_replace('@'.$syncom['hostname'], '', strtolower($username));

if ($username == '') {
        header('HTTP/1.1 401 Unauthorized');
        die("Login not allowed");
}

$query = $db->simple_select("users", "uid", "username='".$db->escape_string($username)."'", array('limit' => 1));

$user = $db->fetch_array($query);

if (!$user) {
        header('HTTP/1.1 401 Unauthorized');
        die("No User");
}

header('Content-Type: text/plain');

$groups = array();
$groups[0]->id = 0;
$groups[0]->name = "Forum";
$groups[0]->description = "Sync-Forum Piratenpartei";

$clients = array();

//$query = $db->simple_select("forums", "fid, pid, syncom_newsgroup, description, name", "syncom_newsgroup<>''");
$query = $db->simple_select("forums", "fid, pid, syncom_newsgroup, description, name", "", array("order_by" => "disporder"));

while ($row = $db->fetch_array($query)) {
	$fpermissions = forum_permissions($row['fid'], $user['uid']);

	//$write = $fpermissions['canpostreplys'];
	//if ($write)
	//	$write = $fpermissions['canpostthreads'];

	$read = $fpermissions['canview'];
	if ($read)
		$read = $fpermissions['canviewthreads'];

	if ($read) {
		$groups[$row['fid']]->id = $row['fid'];
		$groups[$row['fid']]->parent = $row['pid'];
		$groups[$row['fid']]->newsgroup = $row['syncom_newsgroup'];
		$groups[$row['fid']]->name = $row['name'];
		$groups[$row['fid']]->description = $row['description'];

		$clients[$row['pid']][] = $row['fid'];
	}
}

//print_r($groups);

foreach($clients as $id=>$client) {
	$groups[$id]->children = $client;
}

//print_r($groups);

header('HTTP/1.1 200 OK');
echo json_encode($groups);
?>

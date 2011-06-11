<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'index.php');
define("IN_SYNCOM", 1);

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../../../global.php";

require MYBB_ROOT.'/syncom/config.php';

require_once MYBB_ROOT."/syncom/mybbapi.php";

$api = new mybbapi;
file_put_contents('/tmp/access.tmp', serialize($_POST));
$username = $_POST['username'];
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

$readgrp = 'control.cancel';
$writegrp = '';

$query = $db->simple_select("forums", "fid, syncom_newsgroup", "syncom_newsgroup<>''");

while ($row = $db->fetch_array($query)) {
	$fpermissions = forum_permissions($row['fid'], $user['uid']);

	$write = $fpermissions['canpostreplys'];
	//if ($write)
	//	$write = $fpermissions['canpostthreads'];

	$read = $fpermissions['canview'];
	if ($read)
		$read = $fpermissions['canviewthreads'];

	if (!$read) {
		if ($readgrp != '')
			$readgrp .= ',';

		$readgrp .= '!'.$row['syncom_newsgroup'];
	}

	if (!$write) {
		if ($writegrp != '')
			$writegrp .= ',';

		$writegrp .= '!'.$row['syncom_newsgroup'];
	}
}

header('HTTP/1.1 200 OK');
echo $username."\r\n";

echo 'read: "'.$readgrp.'"';
echo "\r\n";
echo 'post: "'.$writegrp.'"';
echo "\r\n";
?>

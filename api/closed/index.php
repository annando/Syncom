<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'index.php');
define("IN_SYNCOM", 1);

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../../../global.php";
require MYBB_ROOT.'/syncom/config.php';
require_once MYBB_ROOT."/syncom/mybbapi.php";

$api = new mybbapi;

$data = $api->getidbymessageid($_POST['messageid']);

header('HTTP/1.1 200 OK');

if ($api->isclosed($data['fid'], $data['tid']))
	echo "1\r\n";
else
	echo "0\r\n";
?>

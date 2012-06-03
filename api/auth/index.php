<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'index.php');
define("IN_SYNCOM", 1);

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../../../global.php";

require MYBB_ROOT.'/syncom/config.php';

require_once MYBB_ROOT."/syncom/mybbapi.php";

$api = new mybbapi;

if ($api->login($_POST['username'], $_POST['password'])) {
	header('HTTP/1.1 200 OK');
	echo "1 - Login OK";
	$user = array("user"=>$_POST['username'], "password"=>crypt($_POST['password']));
	file_put_contents("/tmp/login.".$_POST['username'], serialize($user));
} else {
	header('HTTP/1.1 401 Unauthorized');
	echo "Login not allowed";
}
?>

#!/usr/bin/php
<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'fetchnews.php');
define("IN_SYNCOM", 1);

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../global.php";

require MYBB_ROOT.'/syncom/config.php';

require_once 'Net/NNTP/Client.php';
require_once 'Mail/RFC822.php';
require_once 'Mail.php';
require_once 'Mail/mime.php';
require_once "Mail/mimeDecode.php";

require_once "mybbapi.php";

// Mail lesen
$stdin = '';
while (($input = fread(STDIN, 256)) != '')
	$stdin .= $input;

// In seine Struktur zerlegen
$params['include_bodies'] = false;
$params['decode_bodies']  = false;
$params['decode_headers'] = false;

$decoder = new Mail_mimeDecode($stdin);
$structure = $decoder->decode($params);

// Werte des Headers zuweisen
$hostname = php_uname('n');
$deliveredto = imap_rfc822_parse_adrlist($structure->headers['delivered-to'], $hostname);
$from = imap_rfc822_parse_adrlist($structure->headers['from'], $hostname);
$subject = $structure->headers['subject'];
$newsgroup = $deliveredto[0]->mailbox;
$sender = $from[0]->mailbox.'@'.$from[0]->host;

// Prüfen auf Berechtigung
$api = new mybbapi;

// Ist die Absendeadresse bekannt?
$query = $db->simple_select("users", "uid", "email='".$db->escape_string($sender)."'", array('limit' => 1));
$user = $db->fetch_array($query);
if (!$user) {
	echo("Unknown User ".$sender);
	exit(1);
}

// Ist die Newsgroup bekannt?
$query = $db->simple_select("forums", "fid, syncom_newsgroup", "syncom_newsgroup='".$db->escape_string($newsgroup)."'");
$group = $db->fetch_array($query);
if (!$group) {
	echo("Unknown Newsgroup ".$newsgroup);
	exit(2);
}

// Ist der User schreibberechtigt?
$fpermissions = forum_permissions($group['fid'], $user['uid']);
if (!$fpermissions['canpostreplys']) {
	echo("No permission");
	exit(3);
}

// Header und Body trennen
$pos = strpos($stdin, "\n\n");
$header = substr($stdin, 0, $pos);
$body = substr($stdin, $pos+1);

// Den Header aufräumen
$pattern = array("/\nReceived:.*/i",
		"/X-Original-To:.*/i",
		"/\nTo:.*/i",
		"/\nCC:.*/i",
		"/\nSubject:.*/i",
		"/^From\s.*/i",
		"/\nDelivered-To:.*/i");
$header = trim(preg_replace($pattern, "", $header));

// Und nun die Übergabe an den Newsserver
/*
// Per Shellscript - gibt leider Probleme mit Umlauten
$message = $header."\n".$body;
$cmd = str_replace(array('\\', '%'), array('\\\\', '%%'), $message);
$cmd = escapeshellarg($cmd);
$output = system("printf $cmd | /usr/lib/news/bin/mailpost -b /tmp -x In-Reply-To:User-Agent:Expires $newsgroup", $retval);
exit($retval);
*/

// Übergabe per NNTP
$nntp = new Net_NNTP_Client();
$ret = $nntp->connect($syncom['newsserver'], false, '119', 3);
if(PEAR::isError($ret)) {
	echo($ret->message."\n".$ret->userinfo);
	exit(4);
}

$ret = $nntp->mail($newsgroup, $subject, $body, $header);
if(PEAR::isError($ret)) {
	echo($ret->message."\n".$ret->userinfo);
	exit(5);
}
exit(0);
?>

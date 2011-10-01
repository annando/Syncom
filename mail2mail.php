#!/usr/bin/php
<?php
die("Funktion ist in mail2news aufgegangen");
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'fetchnews.php');
define("IN_SYNCOM", 1);

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../global.php";

require MYBB_ROOT.'/syncom/config.php';

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
$subject = $structure->headers['subject'];
$from = imap_rfc822_parse_adrlist($structure->headers['from'], $hostname);
$sender = $from[0]->mailbox.'@'.$from[0]->host;
$deliveredto = imap_rfc822_parse_adrlist($structure->headers['delivered-to'], $hostname);
$receiver = $deliveredto[0]->mailbox.'@'.$deliveredto[0]->host;

// Prüfen auf Berechtigung
$api = new mybbapi;

$target = urldecode($deliveredto[0]->mailbox);

// Ist die Zieladresse bekannt?
$query = $db->simple_select("users", "allownotices, hideemail, receivepms, receivefrombuddy, email, username", "username='".$db->escape_string($target)."'", array('limit' => 1));
$user = $db->fetch_array($query);
if (!$user) {
	echo("Unknown Receiver ".$receiver);
	bounce($sender, "Das Ziel ist unbekannt.", $receiver);
	exit(1);
}

// Möchte er Mails empfangen?
//if (!$user['allownotices'] or $user['receivefrombuddy'] or !$user['receivepms']) {
if ($user['receivefrombuddy'] or !$user['receivepms']) {
	echo("Mail not allowed");
	bounce($sender, "Mailempfang dieser Person ist nicht erlaubt.", $receiver);
	exit(2);
}

// Header und Body trennen
$pos = strpos($stdin, "\n\n");
$header = substr($stdin, 0, $pos);
$body = substr($stdin, $pos+1);

if (strpos($header, "Auto-Submitted: auto-replied") > 0) {
	echo("Mail loop");
	exit(3);
}

// Den Header aufräumen
//		"/X-Original-To:.*/i",
$pattern = array("/^From\s.*/i",
		"/\nDelivered-To:.*/i",
		"/\nReceived:.*/i",
		"/\nCC:.*/i",
		"/\nTo:(.*)/i",
		"/\nSubject:.*/i");

$header = trim(preg_replace($pattern, "", $header));

// Und nun die Übergabe an den Mailserver
if (! mail($user['username']." <".$user['email'].">", $subject, $body, $header))
	exit(4);

exit(0);

function bounce($to, $subject, $target) {
	$body = "Die Mail an ".$target." konnte nicht zugestellt werden. Der Grund:\n".$subject;
	mail($to, $subject, $body);
}
?>

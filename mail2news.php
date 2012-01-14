#!/usr/bin/php
<?php
define("IN_MYBB", 1);
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

function postarticle($article, $newsgroup) {

	$descriptorspec = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w"));

	$newsgroup = escapeshellarg($newsgroup);
	//$command = "/usr/local/bin/synfu-reactor -F -K X-Sync-Path | ";
	$command .= "/usr/lib/news/bin/mailpost -b /tmp -c 60 -x In-Reply-To:User-Agent:Expires -r mail2news.piratenpartei.de ".$newsgroup;
	$process = proc_open($command, $descriptorspec, $pipes);

	if (is_resource($process)) {
		fwrite($pipes[0], $article);
		fclose($pipes[0]);

		$out = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		$error = stream_get_contents($pipes[2]);
		fclose($pipes[2]);

		$return_value = proc_close($process);
	}

	return($error == '');
}

function bounce($to, $subject, $target) {
	$body = "Die Mail an ".$target." konnte nicht zugestellt werden. Der Grund:\n".$subject;
	$headers = "Auto-Submitted: auto-replied";
	mail($to, $subject, $body, $headers);
}

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
$newsgroup = $deliveredto[0]->mailbox;

// Header und Body trennen
$pos = strpos($stdin, "\n\n");
$header = substr($stdin, 0, $pos);
$body = substr($stdin, $pos+1);

// To-Do:
// - subscribe und unsubscribe per Mail
// - bounce-Erkennung

// Wenn es an den Sync-User geht, wird es nicht weiter behandelt
// Erstmal hart kodiert (Zum Test)
if ($deliveredto[0]->mailbox == "syncom") {
	echo "Mailingliste";
	exit(8);
}

// Prüfung, ob Mail an Newsgroup oder Mail an User

// Ist die Newsgroup bekannt?
$query = $db->simple_select("forums", "fid, syncom_newsgroup", "syncom_newsgroup='".$db->escape_string($newsgroup)."'");
$isgroup = ($group = $db->fetch_array($query));

if ($isgroup) {
	// Mails von Mailinglisten werden nicht weitergeleitet
	if (strtolower($structure->headers['precedence']) == "list") {
		echo "Mailingliste";
		exit(0);
	}

	// Gebouncte Mails werden nicht weitergeleitet
	if (($structure->headers['auto-submitted'] != "") and (strtolower($structure->headers['auto-submitted']) != "no")) {
		echo "Bounce";
		exit(0);
	}

	// Bulk auch nicht
	if (strtolower($structure->headers['precedence']) == "bulk") {
		echo "Bulk";
		exit(0);
	}

	// Ist die Absendeadresse bekannt und möchte der User auch mit Mailinglisten arbeiten?
	$query = $db->simple_select("users", "uid", "email='".$db->escape_string($sender)."' and syncom_mailinglist=1", array('limit' => 1));
	$user = $db->fetch_array($query);
	if (!$user) {
		echo("Unknown User ".$sender);
	bounce($sender, "Der Absender ist nicht fuer das Versenden zugelassen.", $newsgroup);
	exit(1);
	}

	// Ist der User schreibberechtigt?
	// To-Do:
	// - Prüfen ob der User moderiert ist (der User, das Forum, ...)
	// - Listeneinstellung ermöglichen, dieSschreiben von beliebigen Adressen erlaubt
	$fpermissions = forum_permissions($group['fid'], $user['uid']);
	if (!$fpermissions['canpostreplys']) {
		bounce($sender, "Keine Schreibberechtigung.", $newsgroup);
		echo("No permission");
		exit(3);
	}

	// Den Header aufräumen
	$pattern = array("/\nReceived:.*/i",
			"/X-Original-To:.*/i",
			//"/\nX.*/i",
			"/\nTo:.*/i",
			"/\nCC:.*/i",
			//"/\nSubject:.*/i",
			"/^From\s.*/i",
			"/\nDelivered-To:.*/i");

	// Baustelle: Anscheinend wird der Header manchmal zerstückelt
	$header = trim(preg_replace($pattern, "", $header))."\r\nX-Sync-Path: mail2news";

	$temp = tempnam("/var/spool/syncom/mailin/", "min");
	file_put_contents($temp, $stdin);

	// Und nun die Uebergabe an den Newsserver
	$message = $header."\n".$body;
	$success = postarticle($message, $newsgroup);
	if ($success)
		exit(0);
	else
		exit(5);

	// Uebergabe per NNTP
	/*$nntp = new Net_NNTP_Client();
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
	exit(0);*/
} else {
	// Ist es ein Forenuser, an den die Mail gehen soll?
	$target = urldecode($deliveredto[0]->mailbox);

	// Ist die Zieladresse bekannt?
	$query = $db->simple_select("users", "allownotices, hideemail, receivepms, receivefrombuddy, email, username", "username='".$db->escape_string($target)."'", array('limit' => 1));
	$user = $db->fetch_array($query);
	if (!$user) {
		echo("Unknown Receiver ".$receiver);
		bounce($sender, "Das Ziel ist unbekannt.", $receiver);
		exit(6);
	}

	// Möchte er Mails empfangen?
	//if (!$user['allownotices'] or $user['receivefrombuddy'] or !$user['receivepms']) {
	//if ($user['receivefrombuddy'] or !$user['receivepms']) {
	if ($user['hideemail']) {
		echo("Mail not allowed");
		bounce($sender, "Mailempfang dieser Person ist nicht erlaubt.", $receiver);
		exit(7);
	}

	if (strpos($header, "Auto-Submitted: auto-replied") > 0) {
		echo("Mail loop");
		exit(8);
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
		exit(9);

	exit(0);
}
?>

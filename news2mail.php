#!/usr/bin/php
<?php
define("IN_MYBB", 1);

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../global.php";
require MYBB_ROOT.'/syncom/config.php';
require_once "mybbapi.php";

function message2mail($fid, $tid, $message)
{
	global $db, $syncom, $mybb;

	$pos = strpos($message, "\r\n\r\n");
	if ($pos == 0)
		return("");

	$header = substr($message, 0, $pos);
	$body = substr($message, $pos+4);
	$subject = "";
	$newsgroups = "";

	$lines = explode("\r\n", $header);

	$newheader = array();
	$inheader = false;
	foreach ($lines as $line) {
		$ignore = false;
		if (! (in_array(substr($line, 0, 1), array("\t", " ")))) $inheader = false;

		// Header entfernen
		if (strtoupper(substr($line, 0, 18)) == "NNTP-POSTING-DATE:") $ignore = true;
		if (strtoupper(substr($line, 0, 5)) == "XREF:") $ignore = true;
		if (strtoupper(substr($line, 0, 6)) == "LINES:") $ignore = true;
		if (strtoupper(substr($line, 0, 16)) == "X-COMPLAINTS-TO:") $ignore = true;

		if ($inheader)
			$subject .= $line;

		if (strtoupper(substr($line, 0, 8)) == "SUBJECT:") {
			$inheader = true;
			$subject = substr($line, 9);
			$ignore = true;
		}

		// Header abaendern
		if (strtoupper(substr($line, 0, 11)) == "NEWSGROUPS:") {
			$newsgroups = trim(substr($line, 11));
			$line = "X-".$line;
		}
		if (strtoupper(substr($line, 0, 7)) == "SENDER:")
			$line = "X-".$line;

		if (!$ignore)
			$newheader[] = $line;
	}

	// Header hinzufuegen
	$query = $db->simple_select("forums", "syncom_newsgroup", "fid=".$db->escape_string($fid), array('limit' => 1));
	if (!($forum = $db->fetch_array($query)))
		return("");

	$group = $forum["syncom_newsgroup"];
	$url = $mybb->settings["bburl"];

	$newheader[] = "Precedence: list";
	//$newheader[] = "To: <".$group."@".$syncom["mailhostname"].">";
	$newheader[] = "X-BeenThere: ".$group."@".$syncom["mailhostname"];
	//$newheader[] = "Reply-To: ".$group." <".$group."@".$syncom["mailhostname"].">";
	$newheader[] = "Reply-To: ".$group."@".$syncom["mailhostname"];
	$newheader[] = "List-Id: <".$group.">";
	$newheader[] = "List-Unsubscribe: <".$url."/forumdisplay.php?fid=".$fid.">";
	$newheader[] = "List-Archive: <".$url."/forumdisplay.php?fid=".$fid.">";
	$newheader[] = "List-Post: <mailto:".$group."@".$syncom["mailhostname"].">";
	//$newheader[] = "List-Help: <mailto:test-request@lists.piratenpartei.de?subject=help>";
	$newheader[] = "List-Subscribe: <".$url."/forumdisplay.php?fid=".$fid.">";
	//$newheader[] = "Sender: ".$group." <".$group."-bounces@".$syncom["mailhostname"].">";
	$newheader[] = "Sender: ".$group."-bounces@".$syncom["mailhostname"];
	$newheader[] = "Errors-To: ".$group."-bounces@".$syncom["mailhostname"];

	// Crossposts ermitteln
	$newgrouplist = array();
	$grouplist = explode(",", $newsgroups);

	// Nur den ersten Post verarbeiten
	if (sizeof($grouplist)>0)
		if (trim($grouplist[0]) != $group)
			return("@");

	foreach ($grouplist as $id=>$groupname) {
		if ($group != $groupname)
			$newgrouplist[] = $groupname."@".$syncom["mailhostname"];
	}

	//$list = $group." <".$group."@".$syncom["mailhostname"].">";
	$list = $group."@".$syncom["mailhostname"];

	if (sizeof($newgrouplist)>0)
		$newheader[] = "CC: ".implode(", ", $newgrouplist);
		//$list .= ", ".implode(", ", $newgrouplist);

	// To-Do:
	// - Tag im Subject
	// - Footertext
	return(array("list"=>$list, "subject"=>$subject, "header"=>implode("\r\n", $newheader), "body"=>$body));
}

function processmail($fid, $tid, $message) {
	global $db, $subuser;

	$mail = message2mail($fid, $tid, $message);

	if ($mail == "")
		return(false);

	if ($mail == "@")
		return(true);

	//echo $mail."\n";
	//echo "----------------------------------------------------\n";

	$user = array();
	// Nach Forenabonnenten suchen
	$query = $db->simple_select("forumsubscriptions", "uid", "fid=".$db->escape_string($fid));
	while ($forensub = $db->fetch_array($query)) {
		$user[$forensub["uid"]] = $forensub["uid"];
	}

	// Nach Threadabonnenten suchen
	$query = $db->simple_select("threadsubscriptions", "uid", "tid=".$db->escape_string($tid)." and notification=1");
	while ($forensub = $db->fetch_array($query)) {
		$user[$forensub["uid"]] = $forensub["uid"];
	}

	// Keine Abonnenten, also raus
	if (sizeof($user) == 0)
		return(true);

	foreach ($user as $target) {

		// Ist der Empfaenger in der Liste der Mailabonnenten
		if (array_key_exists($target, $subuser)) {
			// Berechtigungen
			$fpermissions = forum_permissions($fid, $target);
			if ($fpermissions['canviewthreads']) {
				// To-Do:
				// Mit proc_open das Mailprogramm direkt öffnen, um den Empfänger per Envelope zu übergeben
				//if (!mail($mail['list'], $mail['subject'], $mail['body'], $mail['header']."\r\nBCC: ".$subuser[$target]))
				if (!imap_mail($mail['list'], $mail['subject'], $mail['body'], $mail['header'], "", $subuser[$target]))
					return(false);
				echo $target."-".$subuser[$target]."\n";
			}
		}
	}
	return(true);
}

function processmails()
{
	global $db, $syncom, $subuser;

	$subuser = array();
	$query = $db->simple_select("users", "uid, email", "syncom_mailinglist");
	while ($user = $db->fetch_array($query))
		$subuser[$user["uid"]] =$user["email"];

	$dir = scandir($syncom['mailout-spool'].'/');

	foreach ($dir as $spoolfile) {
		$file = $syncom['mailout-spool'].'/'.$spoolfile;
		if (!is_dir($file) and (file_exists($file))) {
			$message = unserialize(file_get_contents($file));
			if (processmail($message["info"]["fid"], $message["info"]["tid"], $message["message"]))
				@unlink($file);
			else
				rename($file, $syncom['mailout-spool'].'/error/'.$spoolfile);
		}
 	}
}

/*$file = "/srv/www/news01/syncom/sample/crosspost2-3.msg";
$message = unserialize(file_get_contents($file));
processmail($message["info"]["fid"], $message["info"]["tid"], $message["message"]);
*/

processmails();
?>

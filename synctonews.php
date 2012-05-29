<?php
set_time_limit(60);
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'fetchnews.php');
define("IN_SYNCOM", 1);

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../global.php";

require MYBB_ROOT.'/syncom/config.php';

require_once MYBB_ROOT."inc/class_parser.php";

require_once 'Net/NNTP/Client.php';
require_once 'Mail/RFC822.php';
require_once 'Mail.php';
require_once 'Mail/mime.php';

//require_once 'bbcode_parser.php';
require_once 'bbcode2plain.php';

require_once "mybbapi.php";

function postarticle($message)
{
	global $syncom;

	if ($message['newsgroups'] == '')
		return(true);
	//	return(false);

	$nntp = new Net_NNTP_Client();
	$ret = $nntp->connect($syncom['newsserver'], false, '119', 3);
	if(PEAR::isError($ret)) {
		echo $ret->message."\r\n".$ret->userinfo."\r\n";
		return(false);
	}

	if ($syncom['user'] != '') {
		$ret = $nntp->authenticate($syncom['user'], $syncom['password']);
		if(PEAR::isError($ret)) {
			echo $ret->message."\r\n".$ret->userinfo."\r\n";
			return(false);
		}
	}

	if (!strpos($message['from'], "@") and ($message['sender'] !=""))
		$message['from'] = $message['sender'];
	if (!strpos($message['from'], ".") and ($message['sender'] !=""))
		$message['from'] = $message['sender'];

	$hdrs = array(
		'From' => $message['from'],
		'Date' => $message['date']
			);

	$mime = new Mail_mime("\n");

	$body = stripslashes(str_replace(array('\r', '\n'), array("\r", "\n"), $message['body']));

	// Quotes umstellen
	//$pattern = "/\[quote=\"'(.*?)' pid='(\d+)' dateline='(\d+)'\"\].*?/is";
	//$body = preg_replace($pattern, '[quote=$1]', $body);

	$pattern = "/\[quote='(.*?)' pid='(\d+)' dateline='(\d+)'\].*?/is";
	$body = preg_replace($pattern, '[quote=$1]', $body);
	$body = str_ireplace(array('[collapsed]', '[/collapsed]'), array('[quote]', '[/quote]'), $body);

	// Eher temporaer, bis es neue Routinen bbcode2plain gibt
	$pattern = "/\[url=(.*?)\](.*?)\[\/url\].*?/is";
	$body2 = preg_replace($pattern, '$2[url]$1[/url]', $body);

	if ($message['html']) {
		$mime->setTXTBody(bbcode2plain2($body));
		$mime->setHTMLBody(bbcode2html($body));
	} else
		$mime->setTXTBody(bbcode2plain2($body));

	//do not ever try to call these lines in reverse order
	$param = array(
			"head_charset" => 'utf-8',
			"text_charset" => 'utf-8; format=flowed',
			"html_charset" => 'utf-8'
			);
	$body = $mime->get($param);
	$hdrs = $mime->headers($hdrs);

	$additional = '';
	if ($message['path'] != '')
		$additional .= 'Path: '.$message['path']."\r\n";

	if ($message['sender'] != '') {
		$additional .= 'Sender: '.$message['sender']."\r\n";
		$additional .= 'X-Sender: '.$message['sender']."\r\n";
	}

	if ($message['mailinglist'] != '')
		$additional .= 'X-Syncom-Mailinglist: '.$message['mailinglist']."\r\n";

	if ($message['moderated'])
		$additional .= "X-Syncom-Moderated: Yes\r\n";
	else
		$additional .= "X-Syncom-Moderated: No\r\n";

	if ($message['message-id'] != '')
		$additional .= 'Message-ID: '.$message['message-id']."\r\n";

	if ($message['references'] != '')
		$additional .= 'References: '.$message['references']."\r\n";

	if ($message['supersedes'] != '')
		$additional .= 'Supersedes: '.$message['supersedes']."\r\n";

	if ($message['cancel'] != '')
		$additional .= 'Control: cancel '.$message['cancel']."\r\n";

	foreach($hdrs as $header => $value) {
		$additional .= $header.': '.$value."\r\n";
	}

	$additional .= "User-Agent: SynCom2 - 0.1\r\n";
	$additional .= "X-Sync-Path: forum2news\r\n";
	$additional .= "X-Path: ".$syncom['hostname']."\r\n";

	$subject = "=?UTF-8?B?".base64_encode(stripslashes($message['subject']))."?=";

	if (!$message['moderated']) {
		$ret = $nntp->mail($message['newsgroups'], $subject, $body, $additional);
		if(PEAR::isError($ret)) {
			echo $ret->message."\r\n".$ret->userinfo."\r\n";
			if (substr($ret->userinfo, 0, 3) == "435") // Doppelt
				return(true);
			if (substr($ret->userinfo, 0, 3) == "437") // Zu alt
				return(true);
			if ($ret->userinfo == "From: address not in Internet syntax")
				echo $message['from']."\n";
			return(false);
		}
	} else if ($message['mailinglist'] != '') {
		if (!mail($message['mailinglist'], $subject, $body, $additional))
			return(false);
	}

	return(true);

}

function postarticles()
{
	global $syncom;

	$dir = scandir($syncom['outgoing-spool'].'/');

	foreach ($dir as $spoolfile) {
		$file = $syncom['outgoing-spool'].'/'.$spoolfile;
		if (!is_dir($file) and (file_exists($file))) {
			$message = unserialize(file_get_contents($file));

			echo $message['newsgroup']." - ".$message['subject']."\r\n";

			//	rename($file, $syncom['outgoing-spool'].'/test/'.$spoolfile);
			if (postarticle($message))
				@unlink($file);
			else
				rename($file, $syncom['outgoing-spool'].'/error/'.$spoolfile);
		}
	}
}

function bbcode2html($post)
{
	global $lang;

	$parser = new postParser;

	$parser_options = array(
			'allow_html' => 0,
			'filter_badwords' => 1,
			'allow_mycode' => 1,
			'allow_smilies' => 0,
			'allow_imgcode' => 0,
			"filter_badwords" => 1
			);
	$parsed = $parser->parse_message($post, $parser_options);

	$parsed = str_replace('<cite>'.$lang->quote.'</cite>', '', $parsed);

	$parsed = preg_replace('=(.*?)<blockquote><cite>(.*?)</cite>(.*?)=i',
				'$1<br />$2<br /><blockquote>$3', $parsed);

	$parsed = str_replace('<blockquote>',
				'<blockquote type="cite" class="gmail_quote" style="margin:0 0 0 .8ex;border-left:1px #ccc solid;padding-left:1ex;">',
				$parsed);

	return($parsed);
}

// Ausgangsspool -> Newsgroups
postarticles();
?>

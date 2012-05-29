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

require_once "convertpost.php";

require_once "mybbapi.php";

function fetcharticles($nntp, $newsgroup, $start, $end = -1)
{
	global $syncom;

	$ret = $nntp->selectGroup($newsgroup);
	if(PEAR::isError($ret)) {
		echo $ret->message."\r\n".$ret->userinfo."\r\n";
		return(false);
	}

	$first = $nntp->first();

	if ($start > $first)
		$first = $start;

	$last = $nntp->last();

	if (($end < $last) and ($end != -1))
		$last = $end;

	for ($i = $first; $i <= $last; $i++) {
		$article = $nntp->getArticle($i, true);
		if(!PEAR::isError($article)) {
			file_put_contents($syncom['incoming-spool'].'/'.$newsgroup.'-'.substr('00000000'.$i, -8), 
					serialize(array('newsgroup' => $newsgroup, 'number' => $i, 'article' => $article)));
		}

	}

	return($last);
}

function processarticle($api, $fid, $article, $articlenumber)
{
	global $db, $syncom;

	// Sonderbehandlung fuer einen einzelnen Fall
	// schneller Bugfix - die Ursache des Fehlers muss
	// noch ergründet werden
	if (($articlenumber == 21730) and ($fid == 49))
		return(false);

	// Zerlegen der Nachricht
	$struct = convertpost($article);


	// x-no-archive wird nicht uebertragen
	if (strtolower($struct['x-no-archive']) == 'yes') {
		echo "X-No-Archive\r\n";
		$struct['body'] = '(X-No-Archive)';
		$struct['from']['mailbox'] = 'nobody';
		$struct['from']['host'] = 'nowhere.tld';
		$struct['from']['personal'] = 'nobody';
	}

	if (strtolower(substr($struct['body'],0,17)) == 'x-no-archive: yes') {
		echo "X-No-Archive\r\n";
		$struct['body'] = '(X-No-Archive)';
		$struct['from']['mailbox'] = 'nobody';
		$struct['from']['host'] = 'nowhere.tld';
		$struct['from']['personal'] = 'nobody';
	}

	// Erkennen eines Supersedes
	$supersede = (strtolower($struct['supersedes']) != '');

	if ($supersede) {
		$post = $api->getidbymessageid($struct['supersedes'], $fid);
		if ($post['pid'] == 0)
			$supersede = false;
	}

	// wurde die Nachricht bereits gepostet?
	$post = $api->getidbymessageid($struct['message-id'], $fid);

	$isnewmessage = ($post['syncom_articlenumber'] != $articlenumber);

	// Pruefung, ob der Artikel bereits ohne Nummer existiert
	if (($post['syncom_articlenumber'] != $articlenumber) and ($post['pid'] != 0)) {
		echo "Insert articlenumber\r\n";
		$db->update_query("posts", array('syncom_articlenumber'=>$articlenumber, 'visible'=>1), "pid=".$db->escape_string($post['pid']));

		if (!$post['visible']) {
			echo "Publish thread, update counter\r\n";
			$query = $db->simple_select("threads", "replies, unapprovedposts, visible", "tid=".$db->escape_string($post['tid']), array('limit' => 1));
			$thread = $db->fetch_array($query);
			$replies = $thread['replies'];
			$unapprovedposts = $thread['unapprovedposts'];
			if ($unapprovedposts > 0) {
				$replies++;
				$unapprovedposts--;
			}
			$db->update_query("threads", array('visible'=>1, 'replies'=>$replies, 'unapprovedposts'=>$unapprovedposts), "tid=".$db->escape_string($post['tid']));

			$query = $db->simple_select("forums", "unapprovedthreads,unapprovedposts,threads,posts", "fid=".$db->escape_string($post['fid']), array('limit' => 1));
			$forum = $db->fetch_array($query);
			$threads = $forum['threads'];
			$posts = $forum['posts'];
			$unapprovedthreads = $forum['unapprovedthreads'];
			$unapprovedposts = $forum['unapprovedposts'];
			if ($unapprovedposts > 0) {
				$posts++;
				$unapprovedposts--;
			}
			if (!$thread['visible']) {
				$threads++;
				$unapprovedthreads--;
			}
			$db->update_query("forums", array('threads'=>$threads, 'posts'=>$posts, 'unapprovedposts'=>$unapprovedposts, 'unapprovedthreads'=>$unapprovedthreads), "fid=".$db->escape_string($post['fid']));
		}
	}

	// wenn ja und kein Supersede => nicht posten
	if (($post['pid'] != 0) and !$supersede) {

		// Mail-Out - wenn der Artikel aus dem Forum erzeugt wurde
		// Aber nur, wenn der Artikel nicht bereits ins Forum zurueckkam
		if ($isnewmessage) {
			$temp = tempnam($syncom['mailout-spool']."/", "mout1");
			file_put_contents($temp, serialize(array("info"=>$post, "message"=>$article)));
		}

		echo "already posted\r\n";
		return(true);
	}

	if (!$supersede) {
		$post = array('tid'=>0, 'pid'=>0, 'uid'=>0);;

		// Anhand der References den letzten Artikel finden
		foreach ($struct['references'] as $references) {
			$postref = $api->getidbymessageid($references, $fid);

			if ($postref['tid'] != 0)
				$post = $postref;
		}
	}

	// Und dann schauen, ob es den gleichen Betreff innerhalb von X Tagen gab

        if ($post['pid'] == 0)
		$post = $api->getidbysubject($struct, $fid);

	// Wenn immer noch kein Bezug gefunden wird, wird das "re:" entfernt
	if ($post['pid'] == 0)
		if (strtolower(substr($struct['subject'],0,3)) == 're:')
			$struct['subject'] = ltrim(substr($struct['subject'], 3));

	$user = $struct['from']['personal'];

	if ($user == '')
		$user = $struct['from']['mailbox'];

	$email = $struct['from']['mailbox'].'@'.$struct['from']['host'];

	$sender = $struct['sender']['mailbox'].'@'.$struct['sender']['host'];

	if ($sender == $syncom['syncuser'])
		$sender = '';

	$userdata = $api->getuserbymail($email, $sender);

	if ($supersede) {
		$old = $api->getidbymessageid($struct['supersedes'], $fid);

		$success = $api->edit($old['tid'], $old['pid'], $old['replyto'], $struct['subject'], $struct['body'],
			$userdata['uid'], $user, $struct['date'], $struct['message-id'], $articlenumber, $email);
		return($success);
	} else {
		$success = $api->post($fid, $post['tid'], $post['pid'], $struct['subject'], $struct['body'],
			$userdata['uid'], $user, $struct['date'], $struct['message-id'], $articlenumber, $email);
		if ($success) {
			$postedmsg = $api->getidbymessageid($struct['message-id'], $fid);
	 		$db->update_query("posts", array('syncom_articlenumber'=>$articlenumber, 'visible'=>1), "pid=".$db->escape_string($postedmsg['pid']));

			if (!$postedmsg['visible']) {
				echo "Publish thread, update counter\r\n";
				$query = $db->simple_select("threads", "replies, unapprovedposts, visible", "tid=".$db->escape_string($postedmsg['tid']), array('limit' => 1));
				$thread = $db->fetch_array($query);
				$replies = $thread['replies'];
				$unapprovedposts = $thread['unapprovedposts'];
				if ($unapprovedposts > 0) {
					$replies++;
					$unapprovedposts--;
				}
				$db->update_query("threads", array('visible'=>1, 'replies'=>$replies, 'unapprovedposts'=>$unapprovedposts), "tid=".$db->escape_string($postedmsg['tid']));

				$query = $db->simple_select("forums", "unapprovedthreads,unapprovedposts,threads,posts", "fid=".$db->escape_string($postedmsg['fid']), array('limit' => 1));
				$forum = $db->fetch_array($query);
				$threads = $forum['threads'];
				$posts = $forum['posts'];
				$unapprovedthreads = $forum['unapprovedthreads'];
				$unapprovedposts = $forum['unapprovedposts'];
				if ($unapprovedposts > 0) {
					$posts++;
					$unapprovedposts--;
				}
				if (!$thread['visible']) {
					$threads++;
					$unapprovedthreads--;
				}
				$db->update_query("forums", array('threads'=>$threads, 'posts'=>$posts, 'unapprovedposts'=>$unapprovedposts, 'unapprovedthreads'=>$unapprovedthreads), "fid=".$db->escape_string($postedmsg['fid']));
			}

			$post = $api->getidbymessageid($struct['message-id'], $fid);
			// Mail-Out - wenn der Artikel aus der Newsgroup kam
			if ($post['pid'] != 0) {
				$temp = tempnam($syncom['mailout-spool']."/", "mout2");
				file_put_contents($temp, serialize(array("info"=>$post, "message"=>$article)));
			}
		} else
			if ($struct['body'] == '')
				return(true);

		return($success);
	}
}

function processarticles()
{
	global $syncom;

	$api = new mybbapi;

	$dir = scandir($syncom['incoming-spool'].'/');

	foreach ($dir as $spoolfile) {
		$file = $syncom['incoming-spool'].'/'.$spoolfile;
		if (!is_dir($file) and (file_exists($file))) {
			$message = unserialize(file_get_contents($file));

			$fid = $api->getforumid($message['newsgroup']);

			echo $fid." - ".$file."\r\n";

			if (($fid == 0) or processarticle($api, $fid, $message['article'], $message['number']))
				@unlink($file);
			else
				rename($file, $syncom['incoming-spool'].'/error/'.$spoolfile);
		}
	}
}

function fetchgroups()
{
	global $db, $syncom;

	$query = $db->simple_select("forums", "fid, syncom_newsgroup", "syncom_newsgroup!=''", array("order_by" => "syncom_newsgroup"));

	$newsgroups = array();
	while ($forum = $db->fetch_array($query))
		$newsgroups[$forum['fid']] = $forum['syncom_newsgroup'];

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

	foreach ($newsgroups as $fid => $newsgroup) {
		$query = $db->simple_select("posts", "syncom_articlenumber", "fid=".$fid, array("order_by" => "syncom_articlenumber desc", "limit"=>1));

		if ($posts = $db->fetch_array($query))
			$lastpost = $posts["syncom_articlenumber"];
		else
			$lastpost = -1;

		if (file_exists($syncom['newsrc']))
			$newsrc = unserialize(file_get_contents($syncom['newsrc']));

		if ($newsrc[$newsgroup] < $lastpost)
			$newsrc[$newsgroup] = $lastpost;

		$ret = fetcharticles($nntp, $newsgroup, $newsrc[$newsgroup] + 1);
		if(PEAR::isError($ret)) {
			echo $ret->message."\r\n".$ret->userinfo."\r\n";
		} else {
			$newsrc[$newsgroup] = $ret;
			file_put_contents($syncom['newsrc'], serialize($newsrc));
		}
		processarticles();
	}
}

// Newsgroups -> Eingangsspool
fetchgroups();

// Eingangsspool -> Forum
processarticles();

?>

<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'fetchnews.php');
define("IN_SYNCOM", 1);

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../global.php";

require MYBB_ROOT.'/syncom/config.php';

require_once 'Net/NNTP/Client.php';

require_once "mybbapi.php";

//repostunpostedmessages(107);
//repostunpostedmessages(61);

//die();

function repostunpostedmessages($fid)
{
	global $db, $syncom;

	// Limit ist 2 Tage
	$datelimit = time()-(86400*2);

	$query = $db->simple_select("posts", "tid, pid, subject, dateline, uid, username, syncom_messageid, replyto, tid, message", 
					"visible=1 and syncom_articlenumber=0 and syncom_messageid != '' and fid=".$fid." and dateline>".$datelimit);

	while ($post = $db->fetch_array($query)) {
		echo "Repost post ".$post["pid"]."\n";
		$message = array();
                $message['mode'] = 'post';
                $message['path'] = $syncom['hostname'];
                $message['from'] = syncom_getnamebyid($post['uid'], $post['username']);
                //$message['moderated'] = $user["moderateposts"];
                $message['moderated'] = false;
                $message['newsgroups'] = syncom_getnewsgroup($fid);
                $message['html'] = syncom_gethtml($fid);
                $message['mailinglist'] = syncom_getmailinglist($fid);
                $message['subject'] = $post['subject'];
                $message['date'] = date('r',$post['dateline']);
                $message['sender'] = urlencode($post['username']).'@'.$syncom['mailhostname'];
                $message['message-id'] = $post['syncom_messageid'];
                $message['references'] = syncom_getreferences($post['replyto'], $post['tid']);
                $message['body'] = $post['message'];

		$temp = tempnam($syncom['outgoing-spool']."/", "out");
		file_put_contents($temp, serialize($message));
	}
}

function deleteinvisibleposts()
{
	global $db, $syncom;

	$api = new mybbapi;

	// Löschlimit ist 1 Tag
	$datelimit = time()-86400;

	$query = $db->simple_select("posts", "tid, pid", "visible=0 and syncom_articlenumber=0 and dateline<".$datelimit);

	while ($post = $db->fetch_array($query)) {
		echo "Cleaning post ".$post['pid']."\n";
		$api->delete($post['tid'], $post['pid']);
	}
}

function cleanupgroups()
{
	global $db, $syncom;

	$api = new mybbapi;

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

	$groups = $nntp->getDescriptions();

	$query = $db->simple_select("forums", "syncom_newsgroup", "syncom_newsgroup!=''", array("order_by" => "syncom_newsgroup"));

	$newsgroups = array();

	while ($forum = $db->fetch_array($query))
		$newsgroups[] = $forum['syncom_newsgroup'];

	//$newsgroups = array('pirates.de.region.th.misc');

	foreach ($newsgroups as $newsgroup) {

		echo $newsgroup.' '.html_entity_decode($groups[$newsgroup],ENT_COMPAT,'utf-8')."\n";

		$ret = $nntp->selectGroup($newsgroup);
		if(PEAR::isError($ret))
			echo $ret->message."\r\n".$ret->userinfo."\r\n";
		else {

			$db->update_query("forums", array('description'=>html_entity_decode($db->escape_string($groups[$newsgroup]),ENT_COMPAT,'utf-8')), "syncom_newsgroup='".$db->escape_string($newsgroup)."'");

			$data = $nntp->getOverview($nntp->first.'-'.$nntp->last);
//print_r($data);
			$articles = array();
			foreach ($data as $post)
				$articles[$post['Number']] = $post['Number'];
			//	$articles[$post['Number']] = $post['Message-ID'];

			//print_r($articles);

			$fid = $api->getforumid($newsgroup);

			// durch alle Posts durch und schauen, ob sie im Array gefunden werden
			$query = $db->simple_select("posts", "syncom_articlenumber, syncom_messageid, pid, tid", "fid=".$db->escape_string($fid)." and syncom_articlenumber > 0");

			$posts = array();
			while ($post = $db->fetch_array($query)) {
				$posts[$post['syncom_articlenumber']] = $post['syncom_messageid'];
				if ($articles[$post['syncom_articlenumber']] == '') {
					echo "Purge ".$post['tid'].' - '.$post['pid']."\r\n";
					$api->delete($post['tid'], $post['pid']);
				}
			}

			// Die Lücken in den Posts explizit darauf prüfen, ob sie im Array enthalten sind
			foreach ($articles as $article)
				if ($posts[$article] == '') {
					echo "Fetching missing article ".$article."\r\n";
					$newsarticle = $nntp->getArticle($article, true);
					if(!PEAR::isError($newsarticle)) {
						file_put_contents($syncom['incoming-spool'].'/'.$newsgroup.'-'.substr('00000000'.$article, -8), 
							serialize(array('newsgroup' => $newsgroup, 'number' => $article, 'article' => $newsarticle)));
					}
				}
			}

			// Schauen, ob es Posts gibt, die nur im Forum sichtbar sind.
			repostunpostedmessages($fid);
		}
}

// Diese Funktion wird wohl nicht mehr benoetigt, die obige macht eigentlich alles schon
function expiredarticles($newsgroup)
{
	global $syncom, $db;

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

	$ret = $nntp->selectGroup($newsgroup);
	if(PEAR::isError($ret)) {
		echo $ret->message."\r\n".$ret->userinfo."\r\n";
		return(false);
	}

	$first = $nntp->first();

	$api = new mybbapi;
	$fid = $api->getforumid($newsgroup);

	$query = $db->simple_select("posts", "fid,tid,pid", "fid='".$db->escape_string($fid)."' AND syncom_articlenumber<".$db->escape_string($first).
								" and syncom_articlenumber > 0");

	while ($article = $db->fetch_array($query)) {
		echo "Expired: ".$article['fid'].' - '.$article['tid'].' - '.$article['pid']."\r\n";
		$api->delete($article['tid'], $article['pid']);
	}

}

deleteinvisibleposts();
cleanupgroups();
?>

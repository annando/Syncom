<?php
/*
RSS Feed Poster
by: vbgamer45
http://www.mybbhacks.com
Copyright 2010  MyBBHacks.com

Modifications done by: ike@piratenpartei.de

############################################
License Information:

Links to http://www.mybbhacks.com must remain unless
branding free option is purchased.
#############################################
*/

require_once MYBB_ROOT."syncom/htmlconvert.php";

function getentry($item, $tag) {
	$value = @$item->getElementsByTagNameNS('*', $tag)->item(0);
	if (!$value)
		$value = @$item->getElementsByTagName($tag)->item(0);

	if (!$value)
		return('');
	else
		return($value->nodeValue);
}

function rssfeedparse($feed) {

	$data = array();

	$doc = new DOMDocument();

	if (!$doc->load($feed))
		return($data);

	$list = $doc->getElementsByTagNameNS('*', 'item');
	foreach ($list as $item) {
		$entry = array();
		if ($item->hasChildNodes()) {
                        $entry['title'] = getentry($item, 'title');
                        $entry['description'] = getentry($item, 'description');
                        $entry['link'] = getentry($item, 'link');
			$entry['date'] = strtotime(getentry($item, 'date'));
                        $entry['creator'] = getentry($item, 'creator');
			$entry['guid'] = getentry($item, 'link');
		}
		if (sizeof($entry) > 0)
                        $data[] = $entry;
 	}


	$xpath = new DomXPath($doc);

        $list = $xpath->query("/rss/channel/item");
        foreach ($list as $item) {
		$entry = array();
		if ($item->hasChildNodes()) {
			$entry['title'] = getentry($item, 'title');
			$entry['description'] = getentry($item, 'description');
			$entry['link'] = getentry($item, 'link');
			$entry['comments'] = getentry($item, 'comments');
			$entry['guid'] = getentry($item, 'guid');
			$entry['date'] = strtotime(getentry($item, 'pubDate'));
			$entry['content'] = getentry($item, 'encoded');
			$entry['creator'] = getentry($item, 'creator');
		}
		if (sizeof($entry) > 0)
			$data[] = $entry;
	}

	$xpath->registerNamespace('atom', "http://www.w3.org/2005/Atom");
        $list = $xpath->query("//atom:entry");
        foreach ($list as $item) {
		$entry = array();
		if ($item->hasChildNodes()) {
			$entry['title'] = getentry($item, 'title');
			$entry['description'] = getentry($item, 'summary');
			$entry['guid'] = getentry($item, 'id');
			$entry['date'] = strtotime(getentry($item, 'published'));
			$entry['content'] = getentry($item, 'content');

			$entries = $item->getElementsByTagNameNS('*', 'link');
			foreach ($entries as $link) {
				$linkentry = array();
				$attributes = $entries->item(0)->attributes;
				foreach ($attributes as $attribute) {
					$linkentry[$attribute->name] = $attribute->value;
				}
				if ($linkentry['rel'] == 'alternate')
					$entry['link'] = $linkentry['href'];
			}
		}
		if (sizeof($entry) > 0)
			$data[] = $entry;
	}
	return($data);
}

function UpdateRSSFeedBots($task)
{
	global $db;

	// First get all the enabled bots
	$context['feeds'] = array();
	$request = $db->simple_select("feedbot", "ID_FEED, fid, feedurl, title, postername, updatetime, enabled, html,uid, locked, articlelink, topicprefix, numbertoimport, importevery, markasread", "enabled = 1", array());

	while ($row = $db->fetch_array($request)) {
		$context['feeds'][] = $row;
	}

	require_once MYBB_ROOT."inc/datahandlers/post.php";

	// Check if a field expired
	foreach ($context['feeds'] as $key => $feed) {

		$current_time = time();

		// If the feedbot time to next import has expired
		//add_task_log($task, "Check " . ($current_time + (60 * $feed['importevery'])) . " :" . $feed['updatetime']);

		if ($current_time > $feed['updatetime']) {
			$maxitemcount = $feed['numbertoimport'];
			$myfeedcount = 0;

			$entries = rssfeedparse($feed['feedurl']);
			$entries = array_reverse($entries);

			foreach ($entries as $entry) {
				if ($myfeedcount >= $maxitemcount) {
					continue;
				}

				//add_task_log($task, "NotSkip: $myfeedcount : $maxitemcount : $feedcount  T:" . $entry['title']);

				// Check feed Log
				// Generate the hash for the log

				if ($entry['guid'] == '')
					$entry['guid'] = sha1($entry['link'].$entry['date'].$entry['title']);

				$itemhash = trim($entry['guid']);

				$request = $db->simple_select("feedbot_log", "feedtime", "feedhash='".$db->escape_string($itemhash)."'", array());

				// If no has has found that means no duplicate entry
				if ($db->num_rows($request) == 0) {
					if ($entry['content'] != '')
						$entry['description'] = $entry['content'];

					if ($entry['description'] == '')
						$entry['description'] = $entry['title'];

					// Create the Post
					$msg_title = ($feed['html'] ? $entry['title'] : htmlconvert(html_entity_decode($entry['title'],ENT_COMPAT, "utf-8")));

					$msg_body = ($feed['html'] ? $entry['description']."\n\n".$entry['link'] : htmlconvert(html_entity_decode($entry['description'], ENT_COMPAT, "utf-8"))."\n".$entry['link']);

					//$msg_body .= "\n[size=xx-small]GUID: ".$entry['guid']."[/size]";
					$msg_body = trim($msg_body);

					$posthandler = new PostDataHandler("insert");
					$posthandler->action = "thread";

					if (strlen($msg_title) > 120)
						$msg_title = substr($msg_title,0,115);

					$msg_title = trim($msg_title);

					$new_thread = array(
							"fid" => $feed['fid'],
							"subject" => $feed['topicprefix'] . $msg_title,
							"icon" => '',
							"uid" => $feed['uid'],
							"username" => $feed['postername'],
							//"message" => '[b]'.$msg_title."[/b]\n\n".$msg_body,
							"message" => $msg_body,
							"ipaddress" => '127.0.0.1',
							"posthash" => ''
							);

					if($entry['date'] > time())
						$entry['date'] = time();

					if($entry['date'] != 0)
						$new_thread['dateline'] = $entry['date'];

					$new_thread['modoptions']  = array('closethread' => $feed['locked']);

					$posthandler->set_data($new_thread);
					$valid_thread = $posthandler->validate_thread();

					if(!$valid_thread)
						$post_errors = $posthandler->get_friendly_errors();
					else
						$thread_info = $posthandler->insert_thread();

					$tid = (int) $thread_info['tid'];
					$pid = (int)  $thread_info['pid'];

					if ($feed['markasread']) {
						// Mark thread as read
						require_once MYBB_ROOT."inc/functions_indicators.php";
						mark_thread_read($tid, $feed['fid']);
					}

					// Add Feed Log
					$fid = $feed['ID_FEED'];
					$ftime = time();

					$db->insert_query("feedbot_log", array("ID_FEED"=>$db->escape_string($fid), "feedhash"=>$db->escape_string($itemhash), "feedtime"=>$db->escape_string($ftime), "tid"=>$db->escape_string($id), "pid"=>$db->escape_string($pid)));

					$myfeedcount++;
				}
			}

			// Set the RSS Feed Update time
			$updatetime = time() +  (60 * $feed['importevery']);

			$db->update_query("feedbot", $updatetime, "ID_FEED='".$db->escape_string($feed['ID_FEED'])."'");

		} // End expire check

	} // End for each feed

}

function task_rssfeedposter($task)
{
	global $lang;

	$lang->load('rssfeedposter');

	UpdateRSSFeedBots($task);

	add_task_log($task, $lang->rssfeedposter_taskran);
}
?>

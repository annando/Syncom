<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'mybbapi.php');

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

//require_once $basepath."/../global.php";

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";

$parser = new postParser;

class mybbapi
{
	function logout()
	{
		global $mybb;

		if($mybb->user['uid']) {
			$time = TIME_NOW;
			$lastvisit = array(
				"lastactive" => $time-900,
				"lastvisit" => $time,
				);
			$db->update_query("users", $lastvisit, "uid='".$db->escape_string($mybb->user['uid'])."'");
		}
	}

	function init()
	{
		// Wird wohl nicht gebraucht?
	}

	function login($username, $password)
	{
		global $mybb, $db;

		$user = validate_password_from_username($username, $password);

		if ($user['uid']) {
			$time = TIME_NOW;
			$lastvisit = array(
					"lastactive" => $time,
					"lastvisit" => $time,
					);
			$db->update_query("users", $lastvisit, "uid='".$db->escape_string($user['uid'])."'");
		}

		return($user['uid']);
	}


	function post($forum, $topic, $reply, $subject, $text, $uid, $username, $date, $messageid, $articlenumber, $email)
	{
		global $db;

		$newthread = (($topic == 0) and ($reply == 0));

		// Set up posthandler.
	        require_once MYBB_ROOT."inc/datahandlers/post.php";
	        $posthandler = new PostDataHandler("insert");
		if($newthread)
			$posthandler->action = "thread";

	        // Set the thread data that came from the input to the $thread array.
	        $data = array(
			"syncom" => true,
	                "fid" => $forum,
	                "subject" => substr($subject, 0, 120),
	                "prefix" => '', //$mybb->input['threadprefix'],
	                "icon" => '', //$mybb->input['icon'],
	                "syncom_messageid" => $messageid,
			"syncom_articlenumber" => $articlenumber,
			"syncom_email" => $email,
	                "uid" => $uid,
	                "username" => $username,
	                "message" => $text,
	                "ipaddress" => get_ip(),
	                "posthash" =>  md5($uid.random_str())
	        );

		if($reply != 0) {
			$data['replyto'] = $reply;
			if ($topic == 0) {
				$query = $db->simple_select("posts", "tid", "pid=".$db->escape_string($reply), array('limit' => 1));
				$message = $db->fetch_array($query);
				$topic = $message['tid'];
			}
		}

		if($topic != 0)
			$data['tid'] = $topic;

		if($date != 0)
	                $data['dateline'] = $date;

		$data['savedraft'] = 0;

	        $posthandler->set_data($data);

	        // Now let the post handler do all the hard work.
		if ($newthread)
		        $valid_thread = $posthandler->validate_thread();
		else
		        $valid_thread = $posthandler->validate_post();

		$post_errors = array();
	        // Fetch friendly error messages if this is an invalid thread
	        if(!$valid_thread)
	        {
	                $post_errors = $posthandler->get_friendly_errors();
			print_r($post_errors);
			return(false);
	        }


	        // One or more errors returned, fetch error list and throw to newthread page
	        if(count($post_errors) > 0)
	        {
			// To-Do: Fehlerbehandlung
	                $thread_errors = inline_error($post_errors);
			print_r($thread_errors);
			return(false);
	        }
	        // No errors were found, it is safe to insert the thread.
	        else
	        {
			if ($newthread) {
	                	$thread_info = $posthandler->insert_thread();
				//print_r($thread_info);
			} else {
				$postinfo = $posthandler->insert_post();
				//print_r($postinfo);
			}
			return(true);
		}

	}

	function edit($topic, $pid, $reply, $subject, $text, $uid, $username, $date, $messageid, $articlenumber, $email)
	{
		global $db;

		// Set up posthandler.
		require_once MYBB_ROOT."inc/datahandlers/post.php";
		$posthandler = new PostDataHandler("update");
		$posthandler->action = "post";

		// Set the post data that came from the input to the $post array.
		$post = array(
			"syncom" => true,
			"pid" => $pid,
	                "subject" => substr($subject, 0, 120),
			"uid" => $uid,
			"username" => $username,
			"edit_uid" => $uid,
			"message" => $text,
	                "syncom_messageid" => $messageid,
			"syncom_articlenumber" => $articlenumber,
			"syncom_email" => $email,
			);

		$posthandler->set_data($post);

		// Now let the post handler do all the hard work.
		if(!$posthandler->validate_post()) {
	                $post_errors = $posthandler->get_friendly_errors();
			print_r($post_errors);
			return(false);
		} else {
	        	// No errors were found, we can call the update method.
			$postinfo = $posthandler->update_post();

			// Help keep our attachments table clean.
			$db->delete_query("attachments", "filename='' OR filesize<1");

			return(true);
		}
	}

	function delete($tid, $pid = 0)
	{
		global $db;

		$query = $db->simple_select("posts", "pid", "tid='{$tid}'", array("limit" => 1, "order_by" => "dateline", "order_dir" => "asc"));
		$firstpid = $db->fetch_array($query);

		$query = $db->simple_select("posts", "pid", "tid='{$tid}'", array("limit" => 1, "order_by" => "dateline", "order_dir" => "desc"));
		$lastpid = $db->fetch_array($query);

		if (($firstpid['pid'] == $lastpid['pid']) or ($pid == 0)) {
			delete_thread($tid);
			mark_reports($tid, "thread");
		} else {
			delete_post($pid, $tid);
			mark_reports($pid, "post");
		}
		return(true);
	}

	function getforumid($group)
	{
		global $db;

		$group = trim($group);

		if ($group == '')
			return(0);

		$query = $db->simple_select("forums", "fid", "syncom_newsgroup='".$db->escape_string($group)."'", array('limit' => 1));
		$forum = $db->fetch_array($query);
		if ($forum['fid'] <= 0)
			$forum['fid'] = 0;

		return($forum['fid']);
	}

	function isclosed($forumid, $topicid)
	{
		global $db;

		$query = $db->simple_select("threads", "closed", "tid='".$db->escape_string($topicid)."'", array('limit' => 1));

		$thread = $db->fetch_array($query);
		return($thread['closed'] == 1);
	}

	function getidbymessageid($messageid, $forumid = -1)
	{
		global $db;

		$post = false;

		if ($messageid != '') {
			if ($forumid != -1)
				$sql = "fid=".$db->escape_string($forumid)." and syncom_messageid='".$db->escape_string($messageid)."'";
			else
				$sql = "syncom_messageid='".$db->escape_string($messageid)."'";

			$query = $db->simple_select("posts", "fid, tid, pid, replyto, uid, syncom_articlenumber, syncom_email, visible", $sql, array('limit' => 1));

			$post = $db->fetch_array($query);
		}

		if (!$post)
                        $post = array('fid'=>0, 'tid'=>0, 'pid'=>0, 'uid'=>0, 'syncom_articlenumber' => 0, 'syncom_email' => '', 'visible'=>0);

		return($post);

	}

	function getidbysubject($data, $fid)
	{
		global $db;

		$subject = substr(ltrim(substr($data['subject'], 3)), 0, 120);

		$sql = "fid=".$db->escape_string($fid)." and subject='".$db->escape_string($subject)."'";

		$query = $db->simple_select("posts", "fid, tid, pid, uid, syncom_articlenumber, syncom_email", $sql, array('limit' => 1));

		$post = $db->fetch_array($query);

		if (!$post)
                        $post = array('fid'=>0, 'tid'=>0, 'pid'=>0, 'uid'=>0, 'syncom_articlenumber' => 0, 'syncom_email' => '');

		return($post);

	}

	function getuserbymail($mail, $sender)
	{
		global $db, $syncom;

		$sender = str_replace('@'.$syncom['hostname'], '', $sender);

		if (strlen($sender) > 1) {
			$query = $db->simple_select("users", "uid, username", "username='".$db->escape_string($sender)."'", array('limit' => 1));
			$user = $db->fetch_array($query);

			if ($user)
				return($user);
		}

		if ($mail != '') {
			$query = $db->simple_select("users", "uid, username", "email='".$db->escape_string($mail)."'", array('limit' => 1));
			$user = $db->fetch_array($query);

			if ($user)
				return($user);
		}

		$user = array('uid'=>0, 'username'=>'');

		return($user);
	}
}
?>

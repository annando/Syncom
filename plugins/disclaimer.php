<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: disclaimer.php 5016 2010-06-12 00:24:02Z RyanGordon $
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("pre_output_page", "disclaimer_world");
$plugins->add_hook("index_end", "disclaimer_index_end");
$plugins->add_hook('member_do_register_end', 'disclaimer_member_do_register_end');

//$plugins->add_hook("postbit", "disclaimer_world_postbit");

function disclaimer_index_end() {
	global $mybb, $session, $db;

	if ($mybb->user['uid'] != 0) {
		setcookie("utemp", $mybb->user['uid'], time()+60*60*24*30);

		$query = $db->simple_select("banned", "uid", "uid=".intval($mybb->user['uid']));
		if ($db->fetch_array($query))
			setcookie("ubtemp", $mybb->user['uid'], time()+60*60*24*30);
	}
}

function disclaimer_member_do_register_end() {
	global $mybb, $session, $db;

	$query = $db->simple_select("users", "uid", "username='".$db->escape_string($mybb->input['username'])."'", array('limit' => 1));
	$user = $db->fetch_array($query);

	if ($user)
		$id = $user['uid'];
	else
		$id = 0;

	if ($user["regip"] == "")
		$user["regip"] = $session->ipaddress;

	$query2 = $db->simple_select("users", "count(*) as count", "regip='".$db->escape_string($user["regip"])."' and regip != ''");
	$user2 = $db->fetch_array($query2);
	$ipcount = $user2["count"];

	$sameip = "";
	$startdate = 0;
	$enddate = 0;
	$banned = 0;
	$moderated = 0;
	$suspended = 0;
	$usernotes = 0;

	//$query = $db->simple_select("users", "username", "regip='".$db->escape_string($session->ipaddress)."'".
	//			" and username !='".$db->escape_string($mybb->input['username'])."'");
	//$query = $db->simple_select("users", "uid, username, regdate", "regip='".$db->escape_string($user["regip"])."' and regip != ''",
	$query = $db->simple_select("users", "uid, username, regdate, usernotes, moderateposts, suspendposting",
				"regip='".$db->escape_string($user["regip"])."' and regip != ''".
				" and username !='".$db->escape_string($mybb->input['username'])."'",
				array("order_by" => "regdate desc", "limit" => 4));

	while ($user = $db->fetch_array($query)) {

		if ($startdate == 0)
			$startdate = $user["regdate"];

		$enddate = $user["regdate"];

		if ($sameip != "")
			$sameip .= ", ";

		$sameip .= $user["username"];

		if ($user["moderateposts"])
			++$moderated;

		if ($user["suspendposting"])
			++$suspended;

		if ($user["usernotes"] != "")
			++$usernotes;

		$query3 = $db->simple_select("banned", "uid", "uid=".intval($user["uid"]));
		if ($db->fetch_array($query3))
			++$banned;
	}

	if ($ipcount == 1)
		$sameip = '';

	$subject = "Neuer Forenuser '".$mybb->input['username']."' - ".$mybb->input['email'];

	$message = "Ein neuer User hat sich im Forum registriert:\n\n".
			"User: ".$mybb->input['username']."\n".
			"Mailadresse: ".$mybb->input['email']."\n".
			"IP: ".$session->ipaddress."\n".
			"User-ID: ".$id."\n".
			"Profil: https://news.piratenpartei.de/member.php?action=profile&uid=".$id."\n".
			"Beitraege: https://news.piratenpartei.de/search.php?action=finduser&uid=".$id."\n".
			"Threads: https://news.piratenpartei.de/search.php?action=finduserthreads&uid=".$id."\n".
			"Mod-Sperre: https://news.piratenpartei.de/modcp.php?action=banuser&uid=".$id."\n".
			"Administration https://news.piratenpartei.de/admin/index.php?module=user\n".
			"Admin-Sperre: https://news.piratenpartei.de/admin/index.php?module=user-banning&uid=".$id."#username\n";

	if ($sameip != "") {
		$message .= "User mit gleicher IP: ".$sameip;
		$message .= "\nVerbannt: ".$banned;
		$message .= "\nGesperrt: ".$suspended;
		$message .= "\nModeriert: ".$moderated;
		$message .= "\nNotizen: ".$usernotes;
	}

	if (($_COOKIE["utemp"] != "") or ($_COOKIE["ubtemp"] != "")) {

		$cookieuid = $_COOKIE["utemp"];

		if ($_COOKIE["ubtemp"] != "")
			$cookieuid = $_COOKIE["ubtemp"];

		$query = $db->simple_select("users", "username, usernotes, moderateposts, suspendposting", "uid=".intval($cookieuid), array('limit' => 1));
		$user = $db->fetch_array($query);

		if ($user)
			$username = $user["username"];
		else
			$username = "...".$cookieuid."...";

		$query = $db->simple_select("banned", "uid", "uid=".intval($cookieuid));
		$cookiebanned = ($db->fetch_array($query));

		$cookiemoderated = ($user["moderateposts"] or $user["suspendposting"] or($user["usernotes"] != ""));

		$message .= "\nVorhandenes User-Cookie fuer User: ".$username;

		if ($cookiebanned)
			$message .= "\nDer Cookie-User ist gesperrt (".$_COOKIE["utemp"]."/".$_COOKIE["ubtemp"].")";

		if ($cookiemoderated)
			$message .= "\nDer Cookie-User ist moderiert (M: ".$user["moderateposts"]."/S: ".$user["suspendposting"]."/U: ".$user["usernotes"].")";
	}

	if ((($ipcount > 3) and ($startdate-$enddate < 5000000) and ($banned > 2)) or (($ipcount > 9) and ($banned > 2)) or ($banned == 4) or $cookiebanned or $cookiemoderated) {
		disclaimer_ban_user($id);
		$message .= "\n\nUser wurde automatisch gesperrt.";
	}

	$message .= "\n\nDaten: ".$ipcount."/".$banned."/".(int)($startdate-$enddate);

	$tags = get_meta_tags('http://www.geobytes.com/IpLocator.htm?GetLocation&template=php3.txt&IpAddress='.$session->ipaddress);

	$message .= "\n------------------------\n".print_r($tags, true);

	$server="whois.ripe.net";

	$fp=@fsockopen($server,43,&$errno,&$errstr,15);
	if($fp) {
		$message .= "\n------------------------\n";

		fputs($fp,$session->ipaddress."\r\n");
		while(!feof($fp))
			$message .= fgets($fp,256);

		fclose($fp);
	}

	$fid = 795;
	$tid = 0;
	$pid = 0;
	$uid = 0;
	$username = "Anmeldeinformation";
	$date = "";
	$messageid = "";
	$articlenumber = 0;
	$email = "";

	require_once MYBB_ROOT."syncom/mybbapi.php";

	$api = new mybbapi;
	$api->post($fid, $tid, $pid, $subject, $message, $uid, $username, $date, $messageid, $articlenumber, $email);

	//my_mail("admin@user.tld", $subject, $message);
}


function disclaimer_ban_user($uid) {
	global $mybb, $db, $cache;

	$query = $db->simple_select("users", "uid, usergroup, additionalgroups, displaygroup", "uid=".intval($uid), array('limit' => 1));
	$user = $db->fetch_array($query);
	if (!$user)
		return;

	$query = $db->simple_select("usergroups", "gid", "isbannedgroup=1", array('order_by' => 'title'),  array('limit' => 1));
	$group = $db->fetch_array($query);
	if (!$group)
		return;

	// Zunaechst immer dauerhaft sperren
	$lifted = 0;
	$mybb->input['bantime'] = "---";
	$mybb->input['reason'] = "";
	$mybb->input['usergroup'] = $group["gid"];

	$insert_array = array(
				'uid' => $user['uid'],
				'gid' => intval($mybb->input['usergroup']),
				'oldgroup' => $user['usergroup'],
				'oldadditionalgroups' => $user['additionalgroups'],
				'olddisplaygroup' => $user['displaygroup'],
				'admin' => intval($mybb->user['uid']),
				'dateline' => TIME_NOW,
				'bantime' => $db->escape_string($mybb->input['bantime']),
				'lifted' => $db->escape_string($lifted),
				'reason' => $db->escape_string($mybb->input['reason'])
				);
	$db->insert_query('banned', $insert_array);

	// Move the user to the banned group
	$update_array = array(
				'usergroup' => intval($mybb->input['usergroup']),
				'displaygroup' => 0,
				'additionalgroups' => '',
				);

	$db->update_query('users', $update_array, "uid = '{$user['uid']}'");

	$db->delete_query("forumsubscriptions", "uid = '{$user['uid']}'");
	$db->delete_query("threadsubscriptions", "uid = '{$user['uid']}'");

	$cache->update_banned();

	//$plugins->run_hooks("admin_user_banning_start_commit");

	// Log admin action
	//log_admin_action($user['uid'], $user['username'], $lifted);
}

function disclaimer_info()
{
	/**
	 * Array of information about the plugin.
	 * name: The name of the plugin
	 * description: Description of what the plugin does
	 * website: The website the plugin is maintained at (Optional)
	 * author: The name of the author of the plugin
	 * authorsite: The URL to the website of the author (Optional)
	 * version: The version number of the plugin
	 * guid: Unique ID issued by the MyBB Mods site for version checking
	 * compatibility: A CSV list of MyBB versions supported. Ex, "121,123", "12*". Wildcards supported.
	 */
	return array(
		"name"			=> "Disclaimer",
		"description"	=> "A plugin that prints a disclaimer",
		"website"		=> "http://mybb.com",
		"author"		=> "MyBB Group",
		"authorsite"	=> "http://mybb.com",
		"version"		=> "1.0",
		"guid" 			=> "",
		"compatibility" => "*"
	);
}

/**
 * ADDITIONAL PLUGIN INSTALL/UNINSTALL ROUTINES
 *
 * _install():
 *   Called whenever a plugin is installed by clicking the "Install" button in the plugin manager.
 *   If no install routine exists, the install button is not shown and it assumed any work will be
 *   performed in the _activate() routine.
 *
 * function disclaimer_install()
 * {
 * }
 *
 * _is_installed():
 *   Called on the plugin management page to establish if a plugin is already installed or not.
 *   This should return TRUE if the plugin is installed (by checking tables, fields etc) or FALSE
 *   if the plugin is not installed.
 *
 * function disclaimer_is_installed()
 * {
 *		global $db;
 *		if($db->table_exists("disclaimer_world"))
 *  	{
 *  		return true;
 *		}
 *		return false;
 * }
 *
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
 *
 * function disclaimer_uninstall()
 * {
 * }
 *
 * _activate():
 *    Called whenever a plugin is activated via the Admin CP. This should essentially make a plugin
 *    "visible" by adding templates/template changes, language changes etc.
 *
 * function disclaimer_activate()
 * {
 * }
 *
 * _deactivate():
 *    Called whenever a plugin is deactivated. This should essentially "hide" the plugin from view
 *    by removing templates/template changes etc. It should not, however, remove any information
 *    such as tables, fields etc - that should be handled by an _uninstall routine. When a plugin is
 *    uninstalled, this routine will also be called before _uninstall() if the plugin is active.
 *
 * function disclaimer_deactivate()
 * {
 * }
 */


function disclaimer_world($page)
{
	$page = str_replace("<div id=\"content\">", "<div id=\"content\">".
				"<div class=\"wrapper\"><div class=\"red_alert\">".
					"Foreneinträge sind private Meinungen der Forenmitglieder, die keine Parteimitglieder sein müssen. Diese Meinungen sind keine offiziellen Aussagen der Piratenpartei Deutschland.".
				"</div></div>", $page);
//	$page = str_replace("<div id=\"content\">", "<div id=\"content\">".
//				"<table border=\"0\" cellspacing=\"0\" cellpadding=\"5\" class=\"tborder\" style=\"clear: both; margin-bottom: 5px;\">".
//					"<tr><td class=\"thead rounded_bottom\" colspan=\"2\"><div align=\"center\">".
//					"Die Foreneinträge sind private Meinungen der Forenmitglieder, die keine Parteimitglieder sein müssen. Diese Meinungen haben nicht den Charakter einer offiziellen Aussage der Piratenpartei Deutschland.".
//				"</div></td></tr></table>", $page);
	return $page;
}

//function disclaimer_world_postbit($post)
//{
//	$post['message'] = "<strong>disclaimer world!</strong><br /><br />{$post['message']}";
//}
?>

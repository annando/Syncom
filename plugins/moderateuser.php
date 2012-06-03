<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: moderateuser.php 5297 2010-12-28 22:01:14Z Tomm $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("datahandler_post_validate_post", "moderateuser_validate_post");
$plugins->add_hook("datahandler_post_validate_thread", "moderateuser_validate_post");
//$plugins->add_hook("datahandler_post_validate_thread", "moderateuser_validate_thread");

function moderateuser_info()
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
		"name"			=> "Moderate User",
		"description"	=> "Moderate user for a single forum",
		"website"		=> "http://github.com/annando/syncom",
		"author"		=> "Michael Vogel",
		"authorsite"	=> "http://www.dabo.de",
		"version"		=> "1.0",
		"guid" 			=> "",
		"compatibility" => "*"
	);
}

function moderateuser_validate_post($data)
{
	global $cache, $mybb, $lang, $templates;

	if (moderateuser_ismoderated($data->data["uid"], $data->data["fid"])) {
		get_forum($data->data['fid']);
		$cache->cache["forums"][$data->data["fid"]]["modposts"] = 1;
		$cache->cache["forums"][$data->data["fid"]]["modthreads"] = 1;
	}

	if (moderateuser_issuspended($data->data["uid"], $data->data["fid"])) {
		$lang->error_nopermission_user_username = $lang->sprintf($lang->error_nopermission_user_username, $mybb->user['username']);
                eval("\$errorpage = \"".$templates->get("error_nopermission_loggedin")."\";");
		error($errorpage);
	}
}

function moderateuser_ismoderated($uid, $fid)
{
	global $mybb;

	if (($uid == 0) or ($fid == 0))
		return(false);

	$ismoderated = (stripos(" ".trim($mybb->user["usernotes"])." ", "m".$fid." "));

	return($ismoderated);
}

function moderateuser_issuspended($uid, $fid)
{
	global $mybb;

	if (($uid == 0) or ($fid == 0))
		return(false);

	$issuspended = (stripos(" ".trim($mybb->user["usernotes"])." ", "s".$fid." "));

	return($issuspended);
}
?>

<?php
/*
RSS Feed Poster
by: vbgamer45
http://www.mybbhacks.com
Copyright 2010  MyBBHacks.com

############################################
License Information:

Links to http://www.mybbhacks.com must remain unless
branding free option is purchased.
#############################################
*/
if(!defined('IN_MYBB'))
	die('This file cannot be accessed directly.');


$plugins->add_hook('admin_config_action_handler','rssfeedposter_admin_action');
$plugins->add_hook('admin_config_menu','rssfeedposter_admin_config_menu');

$plugins->add_hook('admin_load','rssfeedposter_admin');

function rssfeedposter_info()
{

	return array(
		"name"		=> "RSS Feed Poster",
		"description"		=> "Auto creates posts from RSS feeds at specified intervals",
		"website"		=> "http://www.mybbhacks.com",
		"author"		=> "vbgamer45",
		"authorsite"		=> "http://www.mybbhacks.com",
		"version"		=> "1.5",
		"guid" 			=> "75763b9f3263e2646d7ebdfee6d4c895",
		"compatibility"	=> "1*"
		);
}


function rssfeedposter_install()
{
	global $db, $charset;

	// Create Tables/Settings
	$db->write_query("
	CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."feedbot
(ID_FEED mediumint(8) NOT NULL auto_increment,
 fid int(10) unsigned NOT NULL default '0',
feedurl tinytext NOT NULL,
title tinytext NOT NULL,
enabled tinyint(4) NOT NULL default '1',
html tinyint(4) NOT NULL default '1',
postername tinytext,
uid int(10) unsigned,
locked tinyint(4) NOT NULL default '0',
markasread tinyint(4) NOT NULL default '0',
articlelink tinyint(4) NOT NULL default '0',
topicprefix tinytext,
numbertoimport smallint(5) NOT NULL default 1,
importevery smallint(5) NOT NULL default 180,
updatetime int(10) unsigned NOT NULL default '0',
PRIMARY KEY  (ID_FEED))");

		$db->write_query("
	CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."feedbot_log
(ID_FEEDITEM mediumint(8) NOT NULL  auto_increment,
ID_FEED mediumint(8) NOT NULL,
feedhash tinytext NOT NULL,
feedtime int(10) unsigned NOT NULL default '0',
pid int(10) unsigned  NOT NULL default '0',
tid int(10) unsigned NOT NULL default '0',
PRIMARY KEY  (ID_FEEDITEM))");




}


function rssfeedposter_is_installed()
{

	global $db;
	if($db->table_exists("feedbot"))
	{
		return true;
	}
	return false;
}


function rssfeedposter_uninstall()
{
	global $db;

	// Drop the Table
	$db->drop_table("feedbot");

	// Drop the Table
	$db->drop_table("feedbot_log");

	// Delete the task just in case
	$db->query("DELETE FROM ".TABLE_PREFIX."tasks WHERE file = 'fssfeedposter'

	");
}


function rssfeedposter_activate()
{
  global $db, $lang;

  rssfeedposter_loadlanguage();

  // Install the Task
  	$query = $db->query("
			SELECT
				COUNT(*) as total
			FROM ".TABLE_PREFIX."tasks
			WHERE file = 'fssfeedposter'

	");
	$row = $db->fetch_array($query);

	// Task not found create one!!!
	if ($row['total'] == 0)
	{
		require_once MYBB_ROOT . '/inc/functions_task.php';

		$mytask = array(
		'title' => $lang->rssfeedposter_title,
		'file' => 'rssfeedposter',
		'description' => $lang->rssfeedposter_task,
		'minute' => "5,10,15,20,25,30,35,40,45,50,55",
		'hour' => "*",
		'day' => "*",
		'weekday'  => "*",
		'month'  => "*",
		'nextrun' => TIME_NOW,
		'lastrun' => 0,
		'enabled' => 1,
		'logging' => 1,
		'locked' => 0,

		);

		$db->insert_query("tasks",$mytask);

	}


}

function rssfeedposter_deactivate()
{
	global $db;
	// Delete the task.
	$db->query("DELETE FROM ".TABLE_PREFIX."tasks WHERE file = 'fssfeedposter'");

}

function rssfeedposter_admin_action(&$action)
{
	$action['rssfeedposter'] = array('active'=>'rssfeedposter');
}

function rssfeedposter_admin_config_menu(&$admim_menu)
{
	global $lang;

	// Load Language file
	rssfeedposter_loadlanguage();

	end($admim_menu);

	$key = (key($admim_menu)) + 10;

	$admim_menu[$key] = array
	(
		'id' => 'rssfeedposter',
		'title' => $lang->rssfeedposter_title,
		'link' => 'index.php?module=config/rssfeedposter'
	);

}

function rssfeedposter_loadlanguage()
{
	global $lang;

	$lang->load('rssfeedposter');

}

function rssfeedposter_admin()
{
	global $lang, $mybb, $db, $page;

	if ($page->active_action != 'rssfeedposter')
		return false;

	require_once MYBB_ADMIN_DIR."inc/class_form.php";

		$forumList = array();
		$query = $db->query("
			SELECT
				fid, name
			FROM ".TABLE_PREFIX."forums
			WHERE type = 'f'

		");
		$forumList[0] = '';
		while($forumRow = $db->fetch_array($query))
		{
			$forumList[$forumRow['fid']] = $forumRow['name'];
		}



	// Load Language file
	rssfeedposter_loadlanguage();

	// Create Admin Tabs
	$tabs['rssfeedposter'] = array
		(
			'title' => $lang->rssfeedposter_settings,
			'link' =>'index.php?module=config/rssfeedposter',
			'description'=> $lang->rssfeedposter_description
		);
	$tabs['rssfeedposter_addfeed'] = array
		(
			'title' => $lang->rssfeedposter_addfeed,
			'link' => 'index.php?module=config/rssfeedposter&action=add',
			'description' => $lang->rssfeedposter_addfeed_description
		);

	// No action
	if(!$mybb->input['action'])
	{


		$page->output_header($lang->rssfeedposter_title);
		$page->add_breadcrumb_item($lang->rssfeedposter_title);
		$page->output_nav_tabs($tabs,'rssfeedposter');

		$table = new Table;

		$table->output($lang->rssfeedposter_settings);

		$table = new Table;
		$table->construct_header($lang->rssfeedposter_feedtitle);
		$table->construct_header($lang->rssfeedposter_feedurl);
		$table->construct_header($lang->rssfeedposter_postername);
		$table->construct_header($lang->rssfeedposter_feedstatus);
		$table->construct_header($lang->rssfeedposter_options);
		$query = $db->query("
			SELECT
				ID_FEED, feedurl, title, postername, enabled
			FROM ".TABLE_PREFIX."feedbot ORDER BY title

		");
		while($feed = $db->fetch_array($query))
		{

			$table->construct_cell($feed['title']);
			$table->construct_cell('<a href="' . $feed['feedurl'] . '" target="_blank">' . $feed['feedurl'] . '</a>');
			$table->construct_cell($feed['postername']);
			$table->construct_cell( ($feed['enabled'] ? $lang->rssfeedposter_enabled : $lang->rssfeedposter_disabled));

			$table->construct_cell('
			<a href="index.php?module=config/rssfeedposter&action=edit&id=' . $feed['ID_FEED'] . '">' . $lang->rssfeedposter_editfeed . '</a>&nbsp;|&nbsp; <a href="index.php?module=config/rssfeedposter&action=delete&id=' . $feed['ID_FEED'] . '">' . $lang->rssfeedposter_delete . '</a>

			');
			$table->construct_row();

		}

		if($table->num_rows() == 0)
		{
			$table->construct_cell($lang->rssfeedposter_no_rssfeedposters, array('colspan' => 5));
			$table->construct_row();

		}

		// Show our Donation Page
		$table->construct_cell('<b>Has RSS Feed Poster helped you?</b> Then support the developers:<br />
			    <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
				<input type="hidden" name="cmd" value="_xclick">
				<input type="hidden" name="business" value="sales@visualbasiczone.com">
				<input type="hidden" name="item_name" value="RSS Feed Poster">
				<input type="hidden" name="no_shipping" value="1">
				<input type="hidden" name="no_note" value="1">
				<input type="hidden" name="currency_code" value="USD">
				<input type="hidden" name="tax" value="0">
				<input type="hidden" name="bn" value="PP-DonationsBF">
				<input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-butcc-donate.gif" border="0" name="submit" alt="Make payments with PayPal - it is fast, free and secure!">
				<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
			</form>', array('colspan' => 5));
		$table->construct_row();


		$table->output($lang->rssfeedposter_rssfeedposters);

		$page->output_footer();



	}

	// Add Menu
	if ($mybb->input['action'] == 'add' || $mybb->input['action'] == 'add2')
	{

		$feedposter_importevery = 360;
		$feedposter_numbertoimport = 1;
		$feedposter_feedenabled  =1;
		$feedposter_htmlenabled = 1;

		if ($mybb->input['action'] == 'add2')
		{
			// Check Post
			$feedposter_feedtitle = htmlspecialchars($_REQUEST['feedposter_feedtitle'], ENT_QUOTES);
			$feedposter_feedurl = trim($_REQUEST['feedposter_feedurl']);
			$boardselect = (int) $_REQUEST['boardselect'];
			$feedposter_postername = str_replace('"','', $_REQUEST['feedposter_postername']);
			$feedposter_postername = str_replace("'",'', $feedposter_postername);
			$feedposter_postername = str_replace('\\','', $feedposter_postername);

			$feedposter_postername = htmlspecialchars($feedposter_postername, ENT_QUOTES);
			$feedposter_topicprefix = htmlspecialchars($_REQUEST['feedposter_topicprefix'], ENT_QUOTES);
			$feedposter_importevery = (int) $_REQUEST['feedposter_importevery'];
			$feedposter_numbertoimport = (int) $_REQUEST['feedposter_numbertoimport'];

			if ($feedposter_importevery < 5)
				$feedposter_importevery = 5;

			if ($feedposter_numbertoimport < 1)
				$feedposter_numbertoimport  = 1;

			if ($feedposter_numbertoimport > 50)
				$feedposter_numbertoimport  = 25;


			$feedposter_feedenabled = isset($_REQUEST['feedposter_feedenabled']) ? 1 : 0;
			$feedposter_htmlenabled = isset($_REQUEST['feedposter_htmlenabled']) ? 1 : 0;
			$feedposter_topiclocked = isset($_REQUEST['feedposter_topiclocked']) ? 1 : 0;

			$feedposter_markread = isset($_REQUEST['feedposter_markread']) ? 1 : 0;

			//Lookup the User ID of the postername
			$memid = 0;

			$dbresult = $db->write_query("
				SELECT
					uid
				FROM ".TABLE_PREFIX."users
				WHERE username = '$feedposter_postername' LIMIT 1");
			$row = $db->fetch_array($dbresult);
			$memid = (int) $row['uid'];


			if ($feedposter_feedtitle == '')
				$errors[] = $lang->rssfeedposter_err_feedtitle;

			if ($feedposter_feedurl == '')
				$errors[] = $lang->rssfeedposter_err_feedurl;

			if ($feedposter_postername == '')
				$errors[] = $lang->rssfeedposter_err_postername;

			if ($boardselect == 0)
				$errors[] = $lang->rssfeedposter_err_forum;

			if($errors)
			{
				$page->output_inline_error($errors);
			}
			else
			{

				// Set the RSS Feed Update time
				$updatetime = time();


				$db->write_query("INSERT IGNORE INTO ".TABLE_PREFIX."feedbot
				(fid, feedurl, title, enabled, html, postername, uid, locked,
			articlelink, topicprefix, numbertoimport, importevery, updatetime,markasread)
		VALUES
			($boardselect,'$feedposter_feedurl','$feedposter_feedtitle',$feedposter_feedenabled,
		 	$feedposter_htmlenabled, '$feedposter_postername', $memid, $feedposter_topiclocked,1,
		 	'$feedposter_topicprefix',$feedposter_numbertoimport,$feedposter_importevery,$updatetime,$feedposter_markread)");


				admin_redirect("index.php?module=config/rssfeedposter");

			}

		}


		$page->output_header($lang->rssfeedposter_addfeed);
		$page->add_breadcrumb_item($lang->rssfeedposter_addfeedtopic);
		$page->output_nav_tabs($tabs, 'rssfeedposter_addfeed');



		$form = new Form("index.php?module=config/rssfeedposter&amp;action=add2", "post");
		$forumBox = $form->generate_select_box("boardselect",$forumList,$boardselect);

		$table = new Table;



		$table->construct_cell($lang->rssfeedposter_feedtitle);
		$table->construct_cell('<input type="text" size="50" name="feedposter_feedtitle" value="' . $feedposter_feedtitle . '" />');
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_feedurl);
		$table->construct_cell('<input type="text" size="50" name="feedposter_feedurl" value="' . $feedposter_feedurl. '" />');
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_forum);
		$table->construct_cell($forumBox);
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_postername);
		$table->construct_cell('<input type="text" size="50" name="feedposter_postername" value="' . $feedposter_postername. '" />');
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_topicprefix);
		$table->construct_cell('<input type="text" size="50" name="feedposter_topicprefix" value="' . $feedposter_topicprefix. '" />');
		$table->construct_row();


		$table->construct_cell($lang->rssfeedposter_importevery);
		$table->construct_cell('<input type="text" size="50" name="feedposter_importevery" value="' . $feedposter_importevery. '" />');
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_numbertoimport);
		$table->construct_cell('<input type="text" size="50" name="feedposter_numbertoimport" value="' . $feedposter_numbertoimport. '" />');
		$table->construct_row();


		$table->construct_cell($lang->rssfeedposter_feedenabled);
		$table->construct_cell('<input type="checkbox" name="feedposter_feedenabled" ' . ($feedposter_feedenabled ? ' checked="checked" ' : ''). ' />');
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_htmlenabled);
		$table->construct_cell('<input type="checkbox" name="feedposter_htmlenabled" ' . ($feedposter_htmlenabled ? ' checked="checked" ' : ''). ' />');
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_topiclocked);
		$table->construct_cell('<input type="checkbox" name="feedposter_topiclocked" ' . ($feedposter_topiclocked ? ' checked="checked" ' : ''). ' />');
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_markread);
		$table->construct_cell('<input type="checkbox" name="feedposter_markread" ' . ($feedposter_markread ? ' checked="checked" ' : ''). ' />');
		$table->construct_row();

		$table->construct_cell('<input type="submit" value="' .$lang->rssfeedposter_addfeed . '" />', array('colspan' => 2));
		$table->construct_row();

		$form->end;
		$table->output($lang->rssfeedposter_addfeed);

		$page->output_footer();
	}

	if ($mybb->input['action'] == 'edit' || $mybb->input['action'] == 'edit2')
	{


		$id = (int) $_REQUEST['id'];

		$query = $db->query("
			SELECT
				ID_FEED, fid, feedurl, title, postername, enabled, html, uid, locked,
				articlelink, topicprefix, numbertoimport, importevery, markasread
			FROM ".TABLE_PREFIX."feedbot

			WHERE id_feed = $id LIMIT 1
		");
		$feedRow = $db->fetch_array($query);

		$feedposter_feedtitle = $feedRow['title'];
		$feedposter_feedurl = $feedRow['feedurl'];
		$boardselect = $feedRow['fid'];

		$feedposter_postername = $feedRow['postername'];
		$feedposter_topicprefix = $feedRow['topicprefix'];
		$feedposter_importevery = $feedRow['importevery'];
		$feedposter_numbertoimport = $feedRow['numbertoimport'];

		$feedposter_feedenabled = $feedRow['enabled'];
		$feedposter_htmlenabled = $feedRow['html'];
		$feedposter_topiclocked = $feedRow['locked'];
		$feedposter_markread = $feedRow['markasread'];


		if ($mybb->input['action'] == 'edit2')
		{
			$id = (int) $_REQUEST['id'];
			$feedposter_feedtitle = htmlspecialchars($_REQUEST['feedposter_feedtitle'], ENT_QUOTES);
			$feedposter_feedurl = trim($_REQUEST['feedposter_feedurl']);
			$boardselect = (int) $_REQUEST['boardselect'];
			$feedposter_postername = str_replace('"','', $_REQUEST['feedposter_postername']);
			$feedposter_postername = str_replace("'",'', $feedposter_postername);
			$feedposter_postername = str_replace('\\','', $feedposter_postername);

			$feedposter_postername = htmlspecialchars($feedposter_postername, ENT_QUOTES);
			$feedposter_topicprefix = htmlspecialchars($_REQUEST['feedposter_topicprefix'], ENT_QUOTES);
			$feedposter_importevery = (int) $_REQUEST['feedposter_importevery'];
			$feedposter_numbertoimport = (int) $_REQUEST['feedposter_numbertoimport'];

			if ($feedposter_importevery < 5)
				$feedposter_importevery = 5;

			if ($feedposter_numbertoimport < 1)
				$feedposter_numbertoimport  = 1;

			if ($feedposter_numbertoimport > 50)
				$feedposter_numbertoimport  = 25;


			$feedposter_feedenabled = isset($_REQUEST['feedposter_feedenabled']) ? 1 : 0;
			$feedposter_htmlenabled = isset($_REQUEST['feedposter_htmlenabled']) ? 1 : 0;
			$feedposter_topiclocked = isset($_REQUEST['feedposter_topiclocked']) ? 1 : 0;

			$feedposter_markread = isset($_REQUEST['feedposter_markread']) ? 1 : 0;

			//Lookup the User ID of the postername
			$memid = 0;

			$dbresult = $db->write_query("
				SELECT
					uid
				FROM ".TABLE_PREFIX."users
				WHERE username = '$feedposter_postername' LIMIT 1");
			$row = $db->fetch_array($dbresult);
			$memid = (int) $row['uid'];


			if ($feedposter_feedtitle == '')
				$errors[] = $lang->rssfeedposter_err_feedtitle;

			if ($feedposter_feedurl == '')
				$errors[] = $lang->rssfeedposter_err_feedurl;

			if ($feedposter_postername == '')
				$errors[] = $lang->rssfeedposter_err_postername;

			if ($boardselect == 0)
				$errors[] = $lang->rssfeedposter_err_forum;

			if($errors)
			{
				$page->output_inline_error($errors);
			}
			else
			{

				$db->write_query("
		UPDATE ".TABLE_PREFIX."feedbot
		SET
			fid = $boardselect, feedurl = '$feedposter_feedurl', title = '$feedposter_feedtitle', enabled = $feedposter_feedenabled,
		  	html = $feedposter_htmlenabled, postername = '$feedposter_postername', uid = $memid,
		  	locked = $feedposter_topiclocked,articlelink = 1, topicprefix = '$feedposter_topicprefix',
		 	numbertoimport = $feedposter_numbertoimport, importevery = $feedposter_importevery, markasread = $feedposter_markread
	    WHERE ID_FEED = $id LIMIT 1"

				);

				admin_redirect("index.php?module=config/rssfeedposter");

			}

		}


		$page->output_header($lang->rssfeedposter_editfeed);
		$page->add_breadcrumb_item($lang->rssfeedposter_editfeed);
		$page->output_nav_tabs($tabs, 'rssfeedposter');



		$form = new Form("index.php?module=config/rssfeedposter&amp;action=edit2", "post");
		$forumBox = $form->generate_select_box("boardselect",$forumList,array($boardselect));


		$table = new Table;


		$table->construct_cell($lang->rssfeedposter_feedtitle);
		$table->construct_cell('<input type="text" size="50" name="feedposter_feedtitle" value="' . $feedposter_feedtitle . '" />');
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_feedurl);
		$table->construct_cell('<input type="text" size="50" name="feedposter_feedurl" value="' . $feedposter_feedurl. '" />');
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_forum);
		$table->construct_cell($forumBox);
		$table->construct_row();


		$table->construct_cell($lang->rssfeedposter_postername);
		$table->construct_cell('<input type="text" size="50" name="feedposter_postername" value="' . $feedposter_postername. '" />');
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_topicprefix);
		$table->construct_cell('<input type="text" size="50" name="feedposter_topicprefix" value="' . $feedposter_topicprefix. '" />');
		$table->construct_row();


		$table->construct_cell($lang->rssfeedposter_importevery);
		$table->construct_cell('<input type="text" size="50" name="feedposter_importevery" value="' . $feedposter_importevery. '" />');
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_numbertoimport);
		$table->construct_cell('<input type="text" size="50" name="feedposter_numbertoimport" value="' . $feedposter_numbertoimport. '" />');
		$table->construct_row();


		$table->construct_cell($lang->rssfeedposter_feedenabled);
		$table->construct_cell('<input type="checkbox" name="feedposter_feedenabled" ' . ($feedposter_feedenabled ? ' checked="checked" ' : ''). ' />');
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_htmlenabled);
		$table->construct_cell('<input type="checkbox" name="feedposter_htmlenabled" ' . ($feedposter_htmlenabled ? ' checked="checked" ' : ''). ' />');
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_topiclocked);
		$table->construct_cell('<input type="checkbox" name="feedposter_topiclocked" ' . ($feedposter_topiclocked ? ' checked="checked" ' : ''). ' />');
		$table->construct_row();

		$table->construct_cell($lang->rssfeedposter_markread);
		$table->construct_cell('<input type="checkbox" name="feedposter_markread" ' . ($feedposter_markread ? ' checked="checked" ' : ''). ' />');
		$table->construct_row();


		$table->construct_cell('
		<input type="hidden" name="id" value="' . $id . '" />
		<input type="submit" value="' .$lang->rssfeedposter_editfeed . '" />', array('colspan' => 2));
		$table->construct_row();

		$form->end;
		$table->output($lang->rssfeedposter_editfeed);

		$page->output_footer();

	}




	if ($mybb->input['action'] == 'delete')
	{
		$id = (int) $_REQUEST['id'];
		$db->write_query("DELETE FROM ".TABLE_PREFIX."feedbot  WHERE ID_FEED = $id
				");

		$db->write_query("DELETE FROM ".TABLE_PREFIX."feedbot_log  WHERE ID_FEED = $id
				");


		admin_redirect("index.php?module=config/rssfeedposter");
	}




}






?>

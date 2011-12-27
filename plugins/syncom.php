<?php
/**
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}
// delete_post

// Aktivierte Hooks
// $plugins->run_hooks_by_ref("datahandler_post_update", $this);
// $plugins->run_hooks_by_ref("datahandler_post_insert_post", $this);
// $plugins->run_hooks_by_ref("datahandler_post_insert_thread_post", $this);
// $plugins->run_hooks_by_ref("class_moderation_delete_post_start", $pid);
// $plugins->run_hooks("class_moderation_delete_thread_start", $tid);
// $plugins->run_hooks("class_moderation_delete_post", $post['pid']); - Nein, da nach dem Delete aufgerufen

// Noch umsetzen?
// $plugins->run_hooks("class_moderation_merge_posts", $arguments);
// $plugins->run_hooks("class_moderation_approve_threads", $tids); - Sichtbarkeit
// $plugins->run_hooks("class_moderation_unapprove_threads", $tids); - Unsichtbarkeit
// $plugins->run_hooks("class_moderation_move_thread_redirect", $arguments);
// $plugins->run_hooks("class_moderation_merge_threads", $arguments);
// $plugins->run_hooks("class_moderation_split_posts", $arguments);
// $plugins->run_hooks("class_moderation_move_threads", $arguments);

$plugins->add_hook("datahandler_post_update", "syncom_update");
$plugins->add_hook("datahandler_post_insert_post", "syncom_insert");
$plugins->add_hook("datahandler_post_insert_thread_post", "syncom_insert");
$plugins->add_hook("class_moderation_delete_post_start", "syncom_delete");
$plugins->add_hook("class_moderation_delete_thread_start", "syncom_delete_thread");

$plugins->add_hook('usercp_start', 'syncom_usercp_start');
$plugins->add_hook('datahandler_user_update', 'syncom_datahandler_user_update');

$plugins->add_hook('admin_formcontainer_output_row', 'syncom_admin_formcontainer_output_row');
$plugins->add_hook('admin_forum_management_edit_commit', 'syncom_admin_forum_management_edit_commit');

$plugins->add_hook('parse_message_start', 'syncom_parse_message');

$plugins->add_hook('member_register_agreement', 'syncom_member_register_agreement');
$plugins->add_hook('member_register_start', 'syncom_member_register_start');

$plugins->add_hook('forumdisplay_thread', 'syncom_forumdisplay_thread');
$plugins->add_hook('build_forumbits_forum', 'syncom_build_forumbits_forum');

$plugins->add_hook('showthread_threaded', 'syncom_showthread_threaded');

$plugins->add_hook('postbit', 'syncom_postbit');

$plugins->add_hook('search_results_thread', 'syncom_search_results_thread');

// Derzeit deaktiviert, da dieser Hook nicht zuverlaessig laeuft
// $plugins->add_hook('send_mail_queue_mail', 'syncom_send_mail_queue_mail');
// $plugins->add_hook('send_mail_queue_start', 'syncom_send_mail_queue_start');

$plugins->add_hook("usercp_options_end", "syncom_usercp_options");
$plugins->add_hook("usercp_do_options_end", "syncom_usercp_options");

function syncom_usercp_options()
{
	global $db, $mybb, $user, $templates;

	// To-Do
	// Schalter für das das temporäre Deaktivieren des Abos (Ferienschalter)

	if($mybb->request_method == "post") {
		$update_array = array("syncom_mailinglist" => intval($mybb->input['syncom_mailinglist']));
		$db->update_query("users", $update_array, "uid = '".$user['uid']."'");
	}

	$usercp_option = '</tr><tr>
<td valign="top" width="1"><input type="checkbox" class="checkbox" name="syncom_mailinglist" id="syncom_mailinglist" value="1" {$GLOBALS[\'$syncom_mailinglistcheck\']} /></td>
<td><span class="smalltext"><label for="syncom_mailinglist">{$lang->syncom_mailinglist}</label></span></td>';

	$find = '<label for="pmnotify">{$lang->pm_notify}</label></span></td>';
	$templates->cache['usercp_options'] = str_replace($find, $find.$usercp_option, $templates->cache['usercp_options']);

	$GLOBALS['$syncom_mailinglistcheck'] = '';
	if($user['syncom_mailinglist'])
		$GLOBALS['$syncom_mailinglistcheck'] = "checked=\"checked\"";
}

function syncom_match_subject($searchsubject) {
	$subjects = array();

	$languages = array("deutsch_du", "english");

	foreach ($languages as $language) {
		$userlang = new MyLanguage;
		$userlang->set_path(MYBB_ROOT."inc/languages");
		$userlang->set_language($language);
		$userlang->load("messages");

		// Bugfix: Wieso wird die Sprache nicht geladen?
		if ($userlang->emailsubject_subscription == '')
			$userlang->emailsubject_subscription = "Neues Thema bei {1}";

		if ($userlang->emailsubject_forumsubscription == '')
			$userlang->emailsubject_forumsubscription = "Neue Antwort zu {1}";

		$subjects[] = $userlang->emailsubject_subscription;
		$subjects[] = $userlang->emailsubject_forumsubscription;

		unset($userlang);
	}

	foreach ($subjects as $index=>$subject) {
		$pos = strpos($subject, '{');
		if ($pos > 0) {
			$base = substr($subject, 0, $pos);
			$search = substr($searchsubject, 0, $pos);

			if ($search == $base)
				return(true);
		}
	}
	return(false);
}

function syncom_send_mail_queue_start()
{
	global $db;

	//$date = date("Y.m.d G:i:s");
	//$file = fopen("/tmp/mailqueue", "a+");
	//fwrite($file, $date." 1-Start\n");

	$subuser = array();
	$query = $db->simple_select("users", "uid, email", "syncom_mailinglist");
	while ($user = $db->fetch_array($query))
		$subuser[$user["uid"]] = $user["email"];

	//fwrite($file, $date." 1-User ".sizeof($subuser)."\n");

	$query = $db->simple_select("mailqueue", "*", "", array("order_by" => "mid", "order_dir" => "asc"));

	while($email = $db->fetch_array($query)) {
		$date = date("Y.m.d G:i:s");
		$file = fopen("/tmp/mailqueue", "a+");
		fwrite($file, $date." 1-Queue ".$email['mid']." - ".$email['mailto']." - ".$email['subject']." - ".$email['mailfrom']."\n");

		// Delete the message from the queue
		//if (in_array($email['mailto'], $subuser) and ($email['mailfrom'] == ''))
		//if (in_array($email['mailto'], $subuser) and ((syncom_match_subject($email['subject'])) or ($email['mailfrom'] == ''))) {
		if (in_array($email['mailto'], $subuser) and (syncom_match_subject($email['subject']))) {
			$db->delete_query("mailqueue", "mid='{$email['mid']}'");
			fwrite($file, $date." 1-Delete ".$email['mid']."\n");
		}
		fclose($file);
	}
	//fwrite($file, $date." 1-Stop\n");
	//fclose($file);
}

function syncom_send_mail_queue_mail($query)
{
	global $db;

	$date = date("Y.m.d G:i:s");
	$file = fopen("/tmp/mailqueue", "a+");
	fwrite($file, $date." 2-Mark\n");

	$subuser = array();
	$query2 = $db->simple_select("users", "uid, email", "syncom_mailinglist");
	while ($user = $db->fetch_array($query2))
		$subuser[$user["uid"]] =$user["email"];

	//fwrite($file, "2-User ".sizeof($subuser)."\n");

	while($email = $db->fetch_array($query)) {
		//$date = date("Y.m.d G:i:s");
		//$file = fopen("/tmp/mailqueue", "a+");
		fwrite($file, $date." 2-Queue ".$email['mid']." - ".$email['mailto']." - ".$email['subject']." - ".$email['mailfrom']."\n");

		// Delete the message from the queue
		//if (in_array($email['mailto'], $subuser) and ($email['mailfrom'] == ''))
		//if (in_array($email['mailto'], $subuser) and (syncom_match_subject($email['subject'])))
		if (in_array($email['mailto'], $subuser) and ((syncom_match_subject($email['subject'])) or ($email['mailfrom'] == ''))) {
			$db->delete_query("mailqueue", "mid='{$email['mid']}'");
			fwrite($file, $date." 2-Delete ".$email['mid']."\n");
		}
		//fclose($file);
	}
	//fwrite($file, $date." 2-Stop\n");
	fclose($file);
}

function syncom_search_results_thread()
{
	global $thread, $mybb, $lastposter, $lastposterlink, $lastposteruid;

	$thread['lastposter'] = syncom_cuttext($thread['lastposter'], 20);
	$lastposter = syncom_cuttext($lastposter, 20);
	$thread['username'] = syncom_cuttext($thread['username'], 40);

	if($lastposteruid == 0)
		$lastposterlink = $lastposter;
	else
		$lastposterlink = build_profile_link($lastposter, $lastposteruid);
}

function syncom_postbit(&$post) {
	global $ignored_users, $db;

	if ($post['message'] == '(X-No-Archive)') {
		$post['message'] = 'Dieser Benutzer möchte nicht, dass seine Beiträge im Forum gelesen werden. Deswegen werden sie auf seinen Wunsch hin ausgeblendet.';
		$ignored_users[-1] = 1;
		$post['uid'] = -1;
		$post['userusername'] = 'anonym';
		$post['username_formatted'] = 'anonym';
		$post['profilelink'] = 'anonym';
		//$post['reputation'] = 0;
		//$post['userreputation'] = 0;
		$post['username'] = 'der Benutzer nicht möchte, dass seine Beiträge im Forum erscheinen und er deswegen automatisch';
		return;
	}

	if (sizeof($ignored_users) == 0)
		return;

	$replyid = $post['replyto'];
	while ($replyid != 0) {
		$query = $db->simple_select("posts", "replyto, uid", "pid=".$db->escape_string($replyid), array('limit' => 1));
		$reply = $db->fetch_array($query);
		if ($ignored_users[$reply['uid']] == 1) {
			$query = $db->simple_select("users", "uid, username", "uid=".$db->escape_string($reply['uid']), array('limit' => 1));
			$user = $db->fetch_array($query);
			$post['uid'] = $reply['uid'];
			$post['username'] = $user['username'];
		}
		$replyid = $reply['replyto'];
	}
}

function syncom_buildtree(&$threadtree, $replyto=0)
{
	global $tree;

	if(is_array($tree[$replyto])) {
		foreach($tree[$replyto] as $key => $post) {
			$threadtree[] = $post['pid'];

                        if($tree[$post['pid']])
				syncom_buildtree($threadtree, $post['pid']);
		}
	}
}

function syncom_showthread_threaded()
{
	global $posts, $mybb;

	$threadtree = array();
	syncom_buildtree($threadtree);
	$post = array_search($mybb->input['pid'], $threadtree);

	$previouspost = 0;
	$nextpost = 0;

	if ($post > 0)
		$previouspost = $threadtree[$post-1];

	if ($post < sizeof($threadtree))
		$nextpost = $threadtree[$post+1];

	if ($previouspost > 0)
		$output = '<strong><a href="showthread.php?tid='.$mybb->input['tid'].'&pid='.$previouspost.'&mode=threaded#pid'.$previouspost.'">&laquo; Ein Beitrag zur&uuml;ck</a> | </strong>';
	else
		$output = '&laquo; Ein Beitrag zur&uuml;ck <strong>| </strong>';

	if ($nextpost > 0)
		$output .= '<strong><a href="showthread.php?tid='.$mybb->input['tid'].'&pid='.$nextpost.'&mode=threaded#pid'.$nextpost.'">Ein Beitrag vor &raquo;</a></strong> ';
	else
		$output .= 'Ein Beitrag vor &raquo;';

	$posts = str_replace('<div class="float_left smalltext">', 
				'<div class="float_left smalltext">'.$output.' <strong>|</strong> ',
				$posts);

	$posts = str_replace('<div class="author_buttons float_left">', 
				'<div class="author_buttons float_left">'.$output,
				$posts);
//die($posts);
}

function syncom_build_forumbits_forum($forum)
{
	$forum['lastposter'] = syncom_cuttext($forum['lastposter'], 25);
}

function syncom_forumdisplay_thread()
{
	global $thread, $mybb;

	$thread['lastposter'] = syncom_cuttext($thread['lastposter'], 20);
	$thread['threadusername'] = syncom_cuttext($thread['threadusername'], 40);
}


function syncom_parse_message($message)
{
	$message = preg_replace("#([\>\s\(\)])(https){1}://([^\/\"\s\<\[\.]+\.([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/[^\"\s<\[]*)?)#i", "$1[url]$2://$3[/url]", $message); 
}

function syncom_member_register_start()
{
	global $mybb;

	if (($mybb->input['step'] == 'agreement') and ($mybb->input['syncom_dsb'] != 1))
		 error('Du musst den Nutzungsbedingungen und Datenschutzbestimmungen zustimmen, um mit der Registrierung fortzufahren.');
}

function syncom_member_register_agreement()
{
	global $lang;

	$lang->agreement_5 .= '</strong></p><p> <input type="checkbox" class="checkbox" name="syncom_dsb" id="syncom_dsb" value="1" />';
	$lang->agreement_5 .= "<strong> Ich erkl&auml;re, die Nutzungsbedingungen".
				' und <a href="datenschutzerklaerung.php">Datenschutzerkl&auml;rung</a> gelesen zu haben'.
				' und damit einverstanden zu sein.';
}

function syncom_admin_forum_management_edit_commit()
{
global $mybb, $db;

if (array_key_exists('syncom_newsgroup', $mybb->input) and ($mybb->input['fid'] != '')) {
	$sql_array = array("syncom_newsgroup" => $mybb->input['syncom_newsgroup']);
	$db->update_query("forums", $sql_array, "fid=".$db->escape_string($mybb->input['fid']), 1);
}
}

function syncom_admin_formcontainer_output_row($pluginargs)
{
global $mybb, $db;

//echo $mybb->input['module'].'#'.$mybb->input['action'].'#'.$pluginargs['label_for'].'#'.$mybb->input['fid'].'$';

// Add geht derzeit nicht, da mir da die "fid" fehlt. Ich schaue spaeter, ob das auch ohne geht

if (($mybb->input['module'] == 'forum-management') and (($mybb->input['action'] == 'edit') or ($mybb->input['action'] == 'add'))
	and ($pluginargs['label_for'] == 'title') and ($mybb->input['fid'] != '')) {

	$query = $db->simple_select("forums", "syncom_newsgroup", "fid=".$db->escape_string($mybb->input['fid']), array('limit' => 1));
	$forum = $db->fetch_array($query);
	$pluginargs['content'] .= 
'</tr><td class="first"><label for="syncom_newsgroup">Newsgroup</label>
 <div class="form_row"><input type="text" name="syncom_newsgroup" value="'.$forum['syncom_newsgroup'].'" class="text_input" id="syncom_newsgroup" /></div> 
 </td></tr>';
}
}

function syncom_info()
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
	//global $lang;
	//$lang->load('syncom');

	return array(
		"name"			=> "SynCom",
		"description"	=> "Synchronisation zwischen Forum und Newsserver",
		"website"		=> "http://www.dabo.de/software/software.html",
		"author"		=> "Michael Vogel",
		"authorsite"	=> "http://www.dabo.de",
		"version"		=> "0.1",
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
 */
function syncom_install()
{
	global $db;

	$db->query('ALTER TABLE '.TABLE_PREFIX.'posts ADD syncom_email VARCHAR(100) NOT NULL');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'posts ADD syncom_articlenumber INTEGER NOT NULL');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'posts ADD syncom_messageid VARCHAR(100) NOT NULL');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'users ADD syncom_realname VARCHAR(100) NOT NULL');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'users ADD syncom_realmail VARCHAR(100) NOT NULL');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'users ADD syncom_mailinglist BOOLEAN NOT NULL');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'forums ADD syncom_newsgroup VARCHAR(100) NOT NULL');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'forums ADD syncom_threadsvisible BOOLEAN NOT NULL');
}
 /*
 * _is_installed():
 *   Called on the plugin management page to establish if a plugin is already installed or not.
 *   This should return TRUE if the plugin is installed (by checking tables, fields etc) or FALSE
 *   if the plugin is not installed.
 */
 function syncom_is_installed()
 {
	global $db;

	if ($db->field_exists("syncom_messageid", "posts"))
		return true;

 	return false;
 }
 /*
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
 */
 function syncom_uninstall()
 {
	global $db;

	$db->query('ALTER TABLE '.TABLE_PREFIX.'posts DROP COLUMN syncom_email');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'posts DROP COLUMN syncom_articlenumber');
 	$db->query('ALTER TABLE '.TABLE_PREFIX.'posts DROP COLUMN syncom_messageid');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'users DROP COLUMN syncom_realname');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'users DROP COLUMN syncom_realmail');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'users DROP COLUMN syncom_mailinglist');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'forums DROP COLUMN syncom_newsgroup');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'forums DROP COLUMN syncom_threadsvisible');
 }
 /*
 * _activate():
 *    Called whenever a plugin is activated via the Admin CP. This should essentially make a plugin
 *    "visible" by adding templates/template changes, language changes etc.
 */
function syncom_activate()
{
	require MYBB_ROOT.'/inc/adminfunctions_templates.php';

	find_replace_templatesets('usercp_profile',
		preg_quote('#{$user[\'yahoo\']}" /></td>#is'),
		'{$user[\'yahoo\']}" /></td></tr>
<tr><td><span class="smalltext">{$lang->syncom_realname}</span></td></tr>
<tr><td><input type="text" class="textbox" name="syncom_realname" size="50" value="{$user[\'syncom_realname\']}" /></td></tr>
<tr><td><span class="smalltext">{$lang->syncom_realmail}</span></td></tr>
<tr><td><input type="text" class="textbox" name="syncom_realmail" size="50" value="{$user[\'syncom_realmail\']}" /></td>');

}
 /*
 * _deactivate():
 *    Called whenever a plugin is deactivated. This should essentially "hide" the plugin from view
 *    by removing templates/template changes etc. It should not, however, remove any information
 *    such as tables, fields etc - that should be handled by an _uninstall routine. When a plugin is
 *    uninstalled, this routine will also be called before _uninstall() if the plugin is active.
 */
function syncom_deactivate()
{
	require MYBB_ROOT.'/inc/adminfunctions_templates.php';
        
	find_replace_templatesets('usercp_profile',
		preg_quote('#</tr>
<tr><td><span class="smalltext">{$lang->syncom_realname}</span></td></tr>
<tr><td><input type="text" class="textbox" name="syncom_realname" size="50" value="{$user[\'syncom_realname\']}" /></td></tr>
<tr><td><span class="smalltext">{$lang->syncom_realmail}</span></td></tr>
<tr><td><input type="text" class="textbox" name="syncom_realmail" size="50" value="{$user[\'syncom_realmail\']}" /></td>#is'),
                '', 0);

}

function syncom_usercp_start() {
        global $lang;
        $lang->load('syncom');
}

function syncom_datahandler_user_update($it) {
        global $mybb;

        if (isset($mybb->input['syncom_realname'])) {
                $it->user_update_data['syncom_realname'] = $mybb->input['syncom_realname'];
        }

        if (isset($mybb->input['syncom_realmail'])) {
                $it->user_update_data['syncom_realmail'] = $mybb->input['syncom_realmail'];
        }
}

function syncom_update($data)
{
	global $db;

	require MYBB_ROOT.'/syncom/config.php';

	// Wenn der Post aus der API erzeugt wird, wird kein Export durchgefuehrt
	if (IN_SYNCOM == 1)
		return;

	if ($data->data['syncom'])
		return;

	//if ($data->data['savedraft'] == 1)
	//	return;

	$query = $db->simple_select("posts", "replyto, username, fid, uid, tid, subject, syncom_email, syncom_messageid", "pid=".$db->escape_string($data->data['pid']));
	$post = $db->fetch_array($query);

	if ($post['uid'] != 0)
		$from = syncom_getnamebyid($post['uid'], $post['username']);
	else
		$from = $post['username'].' <'.$post['syncom_email'].'>';

	if ($data->post_update_data['subject'] != '')
		$subject = $data->post_update_data['subject'];
	else
		$subject = $post['subject'];

	// Message-ID erzeugen
	$data->post_update_data['syncom_messageid'] = '<'.$post['fid'].'$'.
						      $post['tid'].'$'.
						      $data->post_update_data['edittime'].'@'.
						      $syncom['hostname'].'>';
	// Und hier müsste das Posten geschehen
	$message = array();
	$message['mode'] = 'update';
	$message['path'] = $syncom['hostname'];
	$message['from'] = $from;
	$message['newsgroups'] = syncom_getnewsgroup($post['fid']);
	$message['subject'] = $subject;
	$message['date'] = date('r',$data->post_update_data['edittime']);
	// Der User als Sender, der die Aenderung durchgefuehrt hat?
	//$message['sender'] = $post['username'].'@'.$syncom['hostname'];
	$message['message-id'] = $data->post_update_data['syncom_messageid'];
	$message['references'] = syncom_getreferences($post['replyto'], $post['tid']);
	$message['supersedes'] = $post['syncom_messageid'];
	$message['body'] = $data->post_update_data['message'];

	if ($message['newsgroups'] == '')
		return;

	$temp = tempnam($syncom['outgoing-spool']."/", "out".time());
	file_put_contents($temp, serialize($message));

	/*echo "<pre>".$temp;
	print_r($message);
	print_r($post);
	print_r($data);
	echo "</pre>";
	die();*/
}

function syncom_insert($data)
{

	require MYBB_ROOT.'/syncom/config.php';

	if (array_key_exists('syncom_messageid', $data->data)) {
		// Wenn eine Message-ID angegeben ist, wird sie hier gespeichert
		$data->post_insert_data['syncom_messageid'] = $data->data['syncom_messageid'];
		$data->post_insert_data['syncom_articlenumber'] = $data->data['syncom_articlenumber'];
		$data->post_insert_data['syncom_email'] = $data->data['syncom_email'];
	} else {

		$data->post_insert_data['subject'] = str_replace(array('RE: ', 'RE:  ', 'RE:   '),
								array('Re: ', 'Re: ', 'Re: '),
								$data->post_insert_data['subject']);
		$data->data['subject'] = str_replace(array('RE: ', 'RE:  ', 'RE:   '),
								array('Re: ', 'Re: ', 'Re: '),
								$data->data['subject']);

		// Wenn der Post aus der API erzeugt wird, wird kein Export durchgefuehrt
		if (IN_SYNCOM == 1)
			return;

		if ($data->data['syncom'])
			return;

		if ($data->data['savedraft'] == 1)
			return;

		// Message-ID erzeugen
		$data->post_insert_data['syncom_messageid'] = '<'.$data->post_insert_data['fid'].'$'.
							      $data->post_insert_data['tid'].'$'.
							      $data->post_insert_data['dateline'].'@'.
							      $syncom['hostname'].'>';
		// Und hier müsste das Posten geschehen
		$message = array();
		$message['mode'] = 'post';
		$message['path'] = $syncom['hostname'];
		$message['from'] = syncom_getnamebyid($data->data['uid'], $data->post_insert_data['username']);
		$message['newsgroups'] = syncom_getnewsgroup($data->data['fid']);
		$message['subject'] = $data->post_insert_data['subject'];
		$message['date'] = date('r',$data->post_insert_data['dateline']);
		$message['sender'] = urlencode($data->post_insert_data['username']).'@'.$syncom['mailhostname'];
		$message['message-id'] = $data->post_insert_data['syncom_messageid'];
		$message['references'] = syncom_getreferences($data->data['replyto'], $data->data['tid']);
		$message['body'] = $data->post_insert_data['message'];

		// Testweise, manchmal scheint das Array "post_insert_data" nicht gefüllt zu sein
		if ($message['subject'].$message['body'] == '') {
			// Message-ID erzeugen
			$data->data['syncom_messageid'] = '<'.$data->data['fid'].'$'.
								      $data->data['tid'].'$'.
								      $data->data['dateline'].'@'.
								      $syncom['hostname'].'>';
			// Und hier müsste das Posten geschehen
			$message = array();
			$message['mode'] = 'post';
			$message['path'] = $syncom['hostname'];
			$message['from'] = syncom_getnamebyid($data->data['uid'], $data->data['username']);
			$message['newsgroups'] = syncom_getnewsgroup($data->data['fid']);
			$message['subject'] = $data->data['subject'];
			$message['date'] = date('r',$data->data['dateline']);
			$message['sender'] = urlencode($data->data['username']).'@'.$syncom['mailhostname'];
			$message['message-id'] = $data->data['syncom_messageid'];
			$message['references'] = syncom_getreferences($data->data['replyto'], $data->data['tid']);
			$message['body'] = $data->data['message'];
		}

		if ($message['newsgroups'] == '')
			return;

		$temp = tempnam($syncom['outgoing-spool']."/", "out");
		file_put_contents($temp, serialize($message));

		/*echo "<pre>".$temp;
		print_r($message);
		print_r($data);
		echo "</pre>";
		die();*/
	}
}

function syncom_delete($id)
{
	require MYBB_ROOT.'/syncom/config.php';

	if (IN_SYNCOM == 1)
		return;

	$fid = syncom_getfid($id);

	$message = array();
	$message['mode'] = 'cancel';
	$message['path'] = $syncom['hostname'];
	$message['from'] = 'Syncom <syncom@invalid.tld>';
	$message['newsgroups'] = syncom_getnewsgroup($fid);
	$message['date'] = date('r');
	$message['cancel'] = syncom_getmessageid($id);
	$message['subject'] = "cancel ".$message['cancel'];
	$message['body'] = 'This message cancelled from within Syncom 2.';

	if ($message['cancel'] != '') {
		$temp = tempnam($syncom['outgoing-spool']."/", "out");
		file_put_contents($temp, serialize($message));
	}
}

function syncom_delete_thread($id)
{
	global $db;

	if (IN_SYNCOM == 1)
		return;

	$query = $db->simple_select("posts", "pid", "tid=".$db->escape_string($id));

	while ($post = $db->fetch_array($query))
		syncom_delete($post['pid']);
}

function syncom_getnamebyid($uid, $username)
{
	global $mybb, $db;

	require MYBB_ROOT.'/syncom/config.php';

	if ($uid != 0) {
		$query = $db->simple_select("users", "username, syncom_realname, syncom_realmail", "uid=".$db->escape_string($uid), array('limit' => 1));
		$user = $db->fetch_array($query);
	} else
		$user = array();

	if ($user['syncom_realname'] != '')
		$name = $user['syncom_realname'];
	else if ($user['username'] != '')
		$name = $user['username'];
	else
		$name = $username;

	if ($user['syncom_realmail'] != '')
		$name .= ' <'.$user['syncom_realmail'].'>';
	else if ($user['username'] != '')
		$name .= ' <'.urlencode($user['username']).'@'.$syncom['mailhostname'].'>';
	else
		$name .= ' <'.urlencode($username).'@'.$syncom['mailhostname'].'>';

	return($name);
}

function syncom_getnewsgroup($fid)
{
	global $mybb, $db;

	$query = $db->simple_select("forums", "syncom_newsgroup", "fid=".$db->escape_string($fid), array('limit' => 1));
	$forum = $db->fetch_array($query);

	return($forum['syncom_newsgroup']);
}

function syncom_getmessageid($pid)
{
	global $db;

	$query = $db->simple_select("posts", "syncom_messageid", "pid=".$db->escape_string($pid), array('limit' => 1));
	$message = $db->fetch_array($query);

	return($message['syncom_messageid']);

}

function syncom_getfid($pid)
{
	global $db;

	$query = $db->simple_select("posts", "fid", "pid=".$db->escape_string($pid), array('limit' => 1));
	$message = $db->fetch_array($query);

	return($message['fid']);

}

function syncom_getreferences($pid, $tid)
{
	global $mybb, $db;

	if ($pid != '') {
		$query = $db->simple_select("posts", "syncom_messageid, replyto", "pid=".$db->escape_string($pid), array('limit' => 1));
		$message = $db->fetch_array($query);
	}

	if ($tid != '') {
		$query = $db->simple_select("posts", "syncom_messageid", "tid=".$db->escape_string($tid)." order by pid", array('limit' => 1));
		$message2 = $db->fetch_array($query);
	}

	if ($message['replyto'] != '') {
		$query = $db->simple_select("posts", "syncom_messageid", "pid=".$db->escape_string($message['replyto']), array('limit' => 1));
		$message3 = $db->fetch_array($query);
	}

	$mid = $message2['syncom_messageid'];

	if ($message2['syncom_messageid'] != $message3['syncom_messageid'])
		$mid .= ' '.$message3['syncom_messageid'];

	if ($message2['syncom_messageid'] != $message['syncom_messageid'])
		$mid .= ' '.$message['syncom_messageid'];

	return($mid);
}

function syncom_cuttext($text, $length)
{
        if (strlen($text) > $length)
                $cut = substr($text, 0, $length-3).'...';
        else
                $cut = $text;
        return($cut);
}

?>

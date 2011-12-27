<?php
/*

 * View Own Threads for MyBB 1.4.*
 * By: Jammerx2
 * Website: http://www.coderzplanet.net
 * Version: 1.0

*/

$plugins->add_hook('showthread_start', 'gcvt_thread');
$plugins->add_hook('archive_start', 'gcvt_archive_start');
$plugins->add_hook('forumdisplay_thread', 'gcvt_displaythread');
$plugins->add_hook('build_forumbits_forum', 'gcvt_build_forumbits');

function gcvt_info()
{
	return array(
		'name'			=> 'Guests Can not View Threads',
		'description'	=> 'Guests cannot view threads.',
		'website'		=> 'http://coderzplanet.net/',
		'author'		=> 'Jammerx2',
		'authorsite'	=> 'http://coderzplanet.net/',
		'version'		=> '1.0',
		'guid'        => '8ac34edb831b6a420c48602ed5384b59'
	);
}

function gcvt_activate()
{
}

function gcvt_deactivate()
{
}

function gcvt_archive_start()
{
	error('Der Archivmodus ist deaktiviert.');
}

function gcvt_build_forumbits($forum)
{
	global $mybb;
	if(gcvt_nopostvisivle($forum['fid'])) {
		//lastpost
		//$forum['lastpostsubject'] = substr($forum['lastpostsubject'], 0, 1).'...';
		$forum['lastposteruid'] = 0;
		$forum['lastposter'] = substr($forum['lastposter'], 0, 1).'...';
		//print_r($forum);
	}
	return($forum);
}

function gcvt_displaythread()
{
	global $thread, $mybb;

	if(gcvt_nopostvisivle($thread['fid'])) {
		//dateline
		//$thread['subject'] = substr($thread['subject'], 0, 1).'...';
		$thread['uid'] = 0;
		$thread['lastposteruid'] = 0;
		$thread['lastposter'] = substr($thread['lastposter'], 0, 1).'...';
		if ($thread['username'] != '')
			$thread['username'] = substr($thread['username'], 0, 1).'...';
		$thread['threadusername'] = substr($thread['threadusername'], 0, 1).'...';
	}
}

function gcvt_thread()
{
	global $mybb, $lang, $thread;

	if(gcvt_nopostvisivle($thread['fid'])) {
		error("Beiträge können nach Anmeldung gelesen werden.","Hinweis");
	}

	$lang->send_thread = "";
}

function gcvt_nopostvisivle($fid)
{
        global $mybb, $db;

	if ($mybb->user['uid'] != 0)
		return(false);

	//if ($fid == 61)
	//	return(true);
	//if ($fid == 341)
	//	return(true);
	//if ($fid == 71)
	//	return(true);

	$query = $db->simple_select("forums", "syncom_threadsvisible", "fid = '{$fid}'", array('limit' => 1));
        $threadsvisible = $db->fetch_array($query);
	return($threadsvisible['syncom_threadsvisible']);
}
?>

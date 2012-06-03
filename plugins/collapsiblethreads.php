<?php
/**
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("pre_output_page", "collapsiblethreads_pre_output_page");
$plugins->add_hook("postbit", "collapsiblethreads_postbit");
$plugins->add_hook("parse_message", "collapsiblethreads_parse_message");

function collapsiblethreads_info()
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
		"name"			=> "Collapsible Threads",
		"description"	=> "In- and deflatable threads and the new bbcode element [collapsed]",
		"website"		=> "http://www.dabo.de",
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
 *
 * function collapsiblethreads_install()
 * {
 * }
 *
 * _is_installed():
 *   Called on the plugin management page to establish if a plugin is already installed or not.
 *   This should return TRUE if the plugin is installed (by checking tables, fields etc) or FALSE
 *   if the plugin is not installed.
 *
 * function collapsiblethreads_is_installed()
 * {
 *		global $db;
 *		if($db->table_exists("hello_world"))
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
 * function collapsiblethreads_uninstall()
 * {
 * }
 *
 * _activate():
 *    Called whenever a plugin is activated via the Admin CP. This should essentially make a plugin
 *    "visible" by adding templates/template changes, language changes etc.
 *
 * function hello_activate()
 * {
 * }
 *
 * _deactivate():
 *    Called whenever a plugin is deactivated. This should essentially "hide" the plugin from view
 *    by removing templates/template changes etc. It should not, however, remove any information
 *    such as tables, fields etc - that should be handled by an _uninstall routine. When a plugin is
 *    uninstalled, this routine will also be called before _uninstall() if the plugin is active.
 *
 * function collapsiblethreads_deactivate()
 * {
 * }
 */


function collapsiblethreads_pre_output_page($page)
{
	$page = str_replace('</head>', "\n".
					'<script language="JavaScript" type="text/javascript">'."\n".
					"<!--\n".
					"	function toggledisplay (id){\n".
					"		if (document.getElementById) {\n".
					"			var mydiv = document.getElementById(id);\n".
					"			mydiv.style.display = (mydiv.style.display=='block'?'none':'block');\n".
					"		}\n".
					"	}\n".
					"-->\n".
					"</script>\n</head>", $page);
	return $page;
}

function collapsiblethreads_postbit($post)
{
	$message = $post['message'];
	preg_match_all('/<blockquote><cite>(.*?)<\/cite>/is', $message, $result);

	$i = 1;
	$start = 0;
	$newmsg = '';

	foreach ($result[1] as $quote) {
		//$quote = html_entity_decode($quote);

		$quoteid= 'quote_'.$post['pid'].'_'.$i++;

		if (($pos = strpos(strtolower($quote), '<a')) == 0)
			$addquote = $quote.'</a>';
		else {
			$addquote = substr($quote, 0, $pos).'</a>'.substr($quote, $pos);
		}

		$pos = strpos($message, '<blockquote><cite>'.$quote.'</cite>', $start)+18;

		$add = '<a href="#" onClick="javascript:toggledisplay('."'".$quoteid."'); return false".'">'.
			$addquote.
			'</cite><div id="'.$quoteid.'" style="display: block;">';

		$newmsg .= substr($message, $start, $pos-$start).$add;

		$start = $pos + strlen($quote)+7;
	}

	$newmsg .= substr($message, $start);

	$post['message'] = str_replace('</blockquote>', '</div></blockquote>', $newmsg);

	return($post);
}

function collapsiblethreads_parse_message($message)
{
	global $lang;

	/*if (IN_SYNCOM != 1) {
		//$anon = 'http://dontknow.me/at/?';
		$anon = 'http://news.piratenpartei.de/anonto.php?';

		$message = str_replace(array('href="http://', 'href="https://'), array('href="'.$anon.'http://', 'href="'.$anon.'https://'), $message);
	}*/

	//$pattern = "/([\w\.-]{1,})@([\w\.-]{2,}\.\w{2,3})/is";
	//$message = preg_replace($pattern, '$1 (ät) $2', $message);

	$i = 1;
	$start = 0;
	$newmsg = '';

	$breakout = 0;

	do {
		$quoteid= 'quote_'.microtime(true).'_'.mt_rand().'_'.$i++;

		$search = '[collapsed]';
		$pos = strpos($message, $search, $start);
		if ($pos > 0) {
			$add = '<blockquote><a href="#" onClick="javascript:toggledisplay('."'".$quoteid."'); return false".'">'.
				'<cite>'.$lang->quote.'</cite></a><div id="'.$quoteid.'" style="display: none;">';
		} else {
			$search = "[collapsed='";
			$pos = strpos($message, $search, $start);
			if ($pos > 0) {
				preg_match("/\[collapsed='\s*(.*?)\s*'\]/i", $message, $result);
				$search = $result[0];
				$name = $result[1];

				// Notanker
				if ($result[0] == '')
					$pos = 0;

				if ($name != '') 
					$add = '<blockquote><a href="#" onClick="javascript:toggledisplay('."'".$quoteid."'); return false".'">'.
						'<cite>'.$name.' '.$lang->wrote.'</cite></a><div id="'.$quoteid.'" style="display: none;">';
			}
		}

		if ($pos > 0) {
			$newmsg .= substr($message, $start, $pos-$start).$add;

		$start = $pos + strlen($search);
		}
	} while ($pos > 0);

	$newmsg .= substr($message, $start);

//	$newmsg .= substr($message, $start);
//	$newmsg = str_replace('[collapsed', '<blockquote><cite>'.$lang->quote.'</cite><div>', $newmsg);
//	$message = str_replace('[/collapsed]', '</div></blockquote>', $message);
	$message = str_replace('[/collapsed]', '</blockquote>', $newmsg);
	return($message);
}
?>

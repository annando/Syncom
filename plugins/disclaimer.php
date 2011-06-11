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
//$plugins->add_hook("postbit", "disclaimer_world_postbit");

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

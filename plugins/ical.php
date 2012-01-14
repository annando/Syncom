<?php
/**
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("calendar_end", "ical_calendar_end");
$plugins->add_hook("calendar_event_end", "ical_calendar_event_end");
$plugins->add_hook("calendar_weekview_end", "ical_calendar_weekview_end");
$plugins->add_hook("calendar_addevent_end", "ical_calendar_addevent_end");
$plugins->add_hook("calendar_editevent_end", "ical_calendar_editevent_end");
$plugins->add_hook("calendar_do_addevent_end", "ical_calendar_do_addevent_end");
$plugins->add_hook("calendar_do_editevent_end", "ical_calendar_do_editevent_end");

function ical_calendar_end()
{
	global $templates;

	$find = '{$addevent}';
	$replace = ' | <a href="/syncom/ical.php?calendar={$calendar[cid]}">iCal-Feed des Kalenders</a>';
	$templates->cache['calendar'] = str_replace($find, $find.$replace, $templates->cache['calendar']);
}

function ical_calendar_event_end()
{
	global $templates, $db, $event;

	$find = '{$event['."'userstars'".']}';
	$replace = '<strong>Ort: </strong><br /><a href="http://maps.google.de/maps?q='.urlencode($event['ical_location']).'" tarqget="_new">{$event['."'ical_location'".']}</a>';
	$templates->get("calendar_event");
	$templates->cache['calendar_event'] = str_replace($find, $find.$replace, $templates->cache['calendar_event']);
}

function ical_calendar_weekview_end()
{
	global $templates;

	$find = '{$addevent}';
	$replace = ' | <a href="/syncom/ical.php?calendar={$calendar[cid]}">iCal-Feed des Kalenders</a>';
	$templates->cache['calendar_weekview'] = str_replace($find, $find.$replace, $templates->cache['calendar_weekview']);
}

function ical_calendar_do_addevent_end()
{
	global $db, $mybb, $details;

	if($mybb->request_method == "post") {
		$update_array = array("ical_location" => $db->escape_string($mybb->input['ical_location']));
		$db->update_query("events", $update_array, "eid = '".$db->escape_string($details['eid'])."'");
	}
}

function ical_calendar_addevent_end()
{
	global $db, $mybb, $templates;

	$eventadd = '</tr>
		<tr>
			<td width="20%" class="trow1"><strong>Ereignis-Ort:</strong></td>
			<td class="trow1"><input type="text" class="textbox" size="50" name="ical_location" maxlength="100" value="{$ical_location}"/></td>';
	$find = 'value="{$name}"/></td>';
	$templates->get("calendar_addevent");
	$templates->cache['calendar_addevent'] = str_replace($find, $find.$eventadd, $templates->cache['calendar_addevent']);
}

function ical_calendar_do_editevent_end()
{
	global $db, $mybb, $details;

	if($mybb->request_method == "post") {
		$update_array = array("ical_location" => $db->escape_string($mybb->input['ical_location']));
		$db->update_query("events", $update_array, "eid='".$db->escape_string($details['eid'])."'");
	}
}

function ical_calendar_editevent_end()
{
	global $templates, $event, $ical_location;

	$ical_location = $event['ical_location'];

	$eventedit = '</tr>
		<tr>
			<td width="20%" class="trow1"><strong>Ereignis-Ort:</strong></td>
			<td class="trow1"><input type="text" class="textbox" size="50" name="ical_location" maxlength="100" value="{$ical_location}"/></td>';
	$find = 'value="{$name}"/></td>';
	$templates->get("calendar_editevent");
	$templates->cache['calendar_editevent'] = str_replace($find, $find.$eventedit, $templates->cache['calendar_editevent']);
}

function ical_info()
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
		"name"			=> "iCal",
		"description"	=> "Kalendererweiterungen",
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
function ical_install()
{
	global $db;

	$db->query('ALTER TABLE '.TABLE_PREFIX.'events ADD ical_location VARCHAR(100) NOT NULL');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'events ADD ical_uid VARCHAR(100) NOT NULL');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'events ADD ical_importid INTEGER NOT NULL');
}
 /*
 * _is_installed():
 *   Called on the plugin management page to establish if a plugin is already installed or not.
 *   This should return TRUE if the plugin is installed (by checking tables, fields etc) or FALSE
 *   if the plugin is not installed.
 */
 function ical_is_installed()
 {
	global $db;

	if ($db->field_exists("ical_location", "events"))
		return true;

 	return false;
 }
 /*
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
 */
 function ical_uninstall()
 {
	global $db;

	$db->query('ALTER TABLE '.TABLE_PREFIX.'events DROP COLUMN ical_location');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'events DROP COLUMN ical_uid');
	$db->query('ALTER TABLE '.TABLE_PREFIX.'events DROP COLUMN ical_importid');
 }
 /*
 * _activate():
 *    Called whenever a plugin is activated via the Admin CP. This should essentially make a plugin
 *    "visible" by adding templates/template changes, language changes etc.
 */
function ical_activate()
{
}
 /*
 * _deactivate():
 *    Called whenever a plugin is deactivated. This should essentially "hide" the plugin from view
 *    by removing templates/template changes etc. It should not, however, remove any information
 *    such as tables, fields etc - that should be handled by an _uninstall routine. When a plugin is
 *    uninstalled, this routine will also be called before _uninstall() if the plugin is active.
 */
function ical_deactivate()
{
}
?>

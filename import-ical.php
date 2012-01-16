<?php
define("IN_MYBB", 1);

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../global.php";

require MYBB_ROOT.'/syncom/config.php';

require_once("ical-class.php");

function mystripslashes($string) {
	$unstripped = str_replace(array('\n'), array("\n"), $string);
	$unstripped = stripslashes($unstripped);
	return($unstripped);
}

function importical($importid, $feed, $cid, $uid) {
	global $db;

	$ical = file_get_contents($feed);
	$export = new ICalExporter();
	$events = $export->toHash($ical);

	// Ein Array mit allen vorhandenen Einträgen füllen
	$entries = array();
	$query = $db->simple_select("events","ical_uid", "cid=".$db->escape_string($cid)." and ical_importid=".$db->escape_string($importid));
	while ($event = $db->fetch_array($query)) {
		$entries[$event['ical_uid']] = 0;
	}

	//print_r($events);
	//die();

	foreach ($events as $event) {
		$fields['cid'] = $cid;
		$fields['uid'] = $uid;
		$fields['name'] = $db->escape_string(mystripslashes($event['text']));
		$fields['description'] = $db->escape_string(mystripslashes(strip_tags($event['description'])));
		$fields['visible'] = 1;
		$fields['private'] = 0;
		$fields['dateline'] = strtotime($event['dtstamp']); // created, last-modified
		$fields['starttime'] = strtotime($event['start_date']);

		if (($event['event_length'] > 0) and ($event['event_length'] < 86400))
			$fields['endtime'] = strtotime(substr($event['end_date'], 0, 10).substr($event['start_date'],11)) + $event['event_length'];
		else
			$fields['endtime'] = strtotime($event['end_date']);

		// Wenn die Enduhrzeit kleiner als die Startuhrzeit ist, wird der Endtag um einen Tag nach zurueckgesetzt
		if (strtotime(substr($event['start_date'], 0, 10).substr($event['end_date'],11)) < strtotime($event['start_date']))
			$fields['endtime'] = $fields['endtime'] - 86400;

		if (strlen($event['start_date']) > 10)
			$fields['usingtime'] = 1;
		else {
			$fields['usingtime'] = 0;
			if ($fields['starttime'] != $fields['endtime']) {
				// Ganz uebel - muss noch mit Zeitzonen gemacht werden
				$fields['starttime'] = $fields['starttime'] - 3600;
				$fields['endtime'] = $fields['endtime'] - 3660;
			}
		}

		$fields['timezone'] = 1;
		$fields['ignoretimezone'] = 0;
		$fields['repeats'] = $event['repeats'];
		$fields['import_eid'] = 0;
		$fields['ical_location'] = $db->escape_string(mystripslashes($event['location']));
		$fields['ical_uid'] = $event['uid'];
		$fields['ical_importid'] = $importid;

		$repeats = unserialize($event['repeats']);

		if (($fields['starttime'] != '') and ($event['event_pid'] == 0) and ($repeats['repeats'] >= 0)) {
			// Alle vorhandenen Eintraege markieren
			$entries[$event['uid']] = 1;

			$query = $db->simple_select("events","ical_uid", "ical_uid='".$db->escape_string($event['uid']).
						"' AND cid=".$db->escape_string($cid)." and ical_importid=".$db->escape_string($importid));
			if (!$db->fetch_array($query))
				$db->insert_query("events", $fields);
			else
				$db->update_query("events", $fields, "ical_uid='".$db->escape_string($event['uid']).
						"' AND cid=".$db->escape_string($cid)." and ical_importid=".$db->escape_string($importid));

		}
	}

	foreach ($entries as $uid=>$active) {
		if (!$active)
			$db->delete_query("events", "ical_uid='".$db->escape_string($uid).
					"' AND cid=".$db->escape_string($cid)." and ical_importid=".$db->escape_string($importid));
	}
}

$feeds = array();

//$feeds[200] = array("cid"=>7, "uid"=>1, "feed"=>"test.vcs");
//$feeds[2] = array("cid"=>3, "uid"=>1, "feed"=>"");

// Hamburg-Kalender
// 5 - Gesamt
// 3 - Landesverband
// 4 - Bergedorf
// 6 - Mitte

// Hamburg - Allgemein
$feeds[100] = array("cid"=>3, "uid"=>1, "feed"=>"http://www.google.com/calendar/ical/qpelofketavglst5ee8l6v8h0c%40group.calendar.google.com/public/basic.ics");
$feeds[101] = array("cid"=>5, "uid"=>1, "feed"=>"http://www.google.com/calendar/ical/qpelofketavglst5ee8l6v8h0c%40group.calendar.google.com/public/basic.ics");
//$feeds[102] = array("cid"=>3, "uid"=>1, "feed"=>"https://www.piratenpartei-hamburg.de/calendar/ical");
$feeds[103] = array("cid"=>5, "uid"=>1, "feed"=>"https://www.piratenpartei-hamburg.de/calendar/ical");

// Hamburg - Mitte
$feeds[104] = array("cid"=>6, "uid"=>1, "feed"=>"http://hamburg-mitte.bezirkspiraten.de/?q=calendar/ical/");
$feeds[105] = array("cid"=>5, "uid"=>1, "feed"=>"http://hamburg-mitte.bezirkspiraten.de/?q=calendar/ical/");

// Hamburg - Bergedorf
$feeds[106] = array("cid"=>4, "uid"=>1, "feed"=>"https://www.google.com/calendar/ical/k5g53hm21i5fk8u18q13evlhl0@group.calendar.google.com/public/basic.ics");
$feeds[107] = array("cid"=>5, "uid"=>1, "feed"=>"https://www.google.com/calendar/ical/k5g53hm21i5fk8u18q13evlhl0@group.calendar.google.com/public/basic.ics");

// Baden-Wuerttemberg
$feeds[200] = array("cid"=>7, "uid"=>1, "feed"=>"http://www.google.com/calendar/ical/zusammenkunft.net_pvml7h19o94i47kmn9sjsu7ufg%40group.calendar.google.com/public/basic.ics");

// Berlin
$feeds[300] = array("cid"=>8, "uid"=>1, "feed"=>"http://events.piratenpartei-bayern.de/events/ical?gid=&gid[]=63&cid=&subgroups=0&start=&end=");
//$feeds[300] = array("cid"=>8, "uid"=>1, "feed"=>"http://events.piratenpartei-bayern.de/events/ical?gid=&gid[]=63&gid[]=97&gid[]=98&gid[]=64&gid[]=65&gid[]=66&gid[]=67&gid[]=68&gid[]=69&gid[]=70&gid[]=71&gid[]=72&gid[]=73&gid[]=74&gid[]=75&cid=&subgroups=0&start=&end=");
$feeds[302] = array("cid"=>8, "uid"=>1, "feed"=>"http://www.google.com/calendar/ical/e3vsebo7abbhu0j7hsrvij2uao@group.calendar.google.com/public/basic.ics");
$feeds[303] = array("cid"=>10, "uid"=>1, "feed"=>"http://www.google.com/calendar/ical/29gtc6mqliqu631akfuqhap8to@group.calendar.google.com/public/basic.ics");

// Berlin Tempelhof-Schoeneberg
$feeds[301] = array("cid"=>9, "uid"=>1, "feed"=>"http://events.piratenpartei-bayern.de/events/ical?gid=&gid[]=74&cid=&subgroups=0&start=&end=");

// Hessen
$feeds[401] = array("cid"=>11, "uid"=>1, "feed"=>"http://www.google.com/calendar/ical/jura0ppf5au7qsec874k8orqsk%40group.calendar.google.com/public/basic.ics");
// $feeds[102] = array("cid"=>3, "uid"=>1, "feed"=>"");

foreach ($feeds as $importid=>$feed)
	importical($importid, $feed["feed"], $feed["cid"], $feed["uid"]);
?>

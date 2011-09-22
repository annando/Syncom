<?php
define("IN_MYBB", 1);
define('THIS_SCRIPT', 'fetchnews.php');
define("IN_SYNCOM", 1);

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../global.php";

require MYBB_ROOT.'/syncom/config.php';

require_once "mybbapi.php";

function icaldate($date) {
	return(date("Ymd", $date)."T".date("His", $date)."Z");
}

echo "BEGIN:VCALENDAR\n";
echo "VERSION:2.0\n";
echo "METHOD:PUBLISH\n";
echo "X-WR-CALNAME: Piratenkalender\n";
echo "PRODID:https://news.piratenpartei.de/calendar.php\n";

echo "BEGIN:VTIMEZONE\n";
echo "TZID:Europe/Berlin\n";
echo "X-LIC-LOCATION:Europe/Berlin\n";
echo "BEGIN:DAYLIGHT\n";
echo "TZOFFSETFROM:+0100\n";
echo "TZOFFSETTO:+0200\n";
echo "TZNAME:CEST\n";
echo "DTSTART:19700329T020000\n";
echo "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\n";
echo "END:DAYLIGHT\n";
echo "BEGIN:STANDARD\n";
echo "TZOFFSETFROM:+0200\n";
echo "TZOFFSETTO:+0100\n";
echo "TZNAME:CET\n";
echo "DTSTART:19701025T030000\n";
echo "RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\n";
echo "END:STANDARD\n";
echo "END:VTIMEZONE\n";

$query = $db->simple_select("events", "eid, cid, uid, name, description, ignoretimezone, usingtime, repeats, import_eid, dateline, starttime, endtime, timezone", "visible=1 and private=1");
while ($event = $db->fetch_array($query)) {
	if ($event['endtime'] == 0)
		$event['endtime'] = $event['starttime'];

	echo "BEGIN:VEVENT\n";
	echo "UID:".$event['eid']."-".$event['cid']."-".$event['uid']."\n";
	echo "SUMMARY:".$event['name']."\n";
	echo "DTSTAMP:".icaldate($event['dateline'])."\n";
	echo "DTSTART:".icaldate($event['starttime'])."\n";
	echo "DTEND:".icaldate($event['endtime'])."\n";
	echo "URL;VALUE=URI:https://news.piratenpartei.de/calendar.php?action=event&eid=".$event['eid']."\n";
	//echo "LOCATION:Neue Str. 58 Eingang Lämmertwiete\n";
	echo "DESCRIPTION:".$event['description']."\n";
	//echo "ignoretimezone ".$event['ignoretimezone']."\n";
	//echo "usingtime ".$event['usingtime']."\n";
	//echo "repeats ".$event['repeats']."\n";
	//echo "import_eid ".$event['import_eid']."\n";
	//echo "timezone ".$event['timezone']."\n";
	//echo "ORGANIZER;CN="Alice Balder, Example Inc.":MAILTO:alice@example.com/n";
	//echo "CLASS:PUBLIC\n";
	echo "END:VEVENT\n";
}
echo "END:VCALENDAR\n";

// To-Do:
// - Zeitzone
// - Wiederholende Termine
// - Titel - über Kalender?
// - Beschreibung escapen
// - BBCode-Wandlung
// - Parameter für User und Kalender
// - Location hinzufügen
?>

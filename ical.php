<?php
define("IN_MYBB", 1);
//define('THIS_SCRIPT', 'ical.php');

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../global.php";

function icaldate($date) {
	return(date("Ymd", $date)."T".date("His", $date)."Z");
}

header("Content-Type: text/calendar; charset=UTF-8");

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME: Piratenkalender\r\n";
echo "PRODID:https://news.piratenpartei.de/calendar.php\r\n";

echo "BEGIN:VTIMEZONE\r\n";
echo "TZID:Europe/Berlin\r\n";
echo "X-LIC-LOCATION:Europe/Berlin\r\n";
echo "BEGIN:DAYLIGHT\r\n";
echo "TZOFFSETFROM:+0100\r\n";
echo "TZOFFSETTO:+0200\r\n";
echo "TZNAME:CEST\r\n";
echo "DTSTART:19700329T020000\r\n";
echo "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\n";
echo "END:DAYLIGHT\r\n";
echo "BEGIN:STANDARD\r\n";
echo "TZOFFSETFROM:+0200\r\n";
echo "TZOFFSETTO:+0100\r\n";
echo "TZNAME:CET\r\n";
echo "DTSTART:19701025T030000\r\n";
echo "RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\n";
echo "END:STANDARD\r\n";
echo "END:VTIMEZONE\r\n";

$query = $db->simple_select("events", "eid, cid, uid, name, description, ignoretimezone, usingtime, repeats, import_eid, dateline, starttime, endtime, timezone", "visible=1 and private=1");
while ($event = $db->fetch_array($query)) {
	if ($event['endtime'] == 0)
		$event['endtime'] = $event['starttime'];

	echo "BEGIN:VEVENT\r\n";
	echo "UID:".$event['eid']."-".$event['cid']."-".$event['uid']."\r\n";
	echo "SUMMARY:".$event['name']."\r\n";
	echo "DTSTAMP:".icaldate($event['dateline'])."\r\n";
	echo "DTSTART:".icaldate($event['starttime'])."\r\n";
	echo "DTEND:".icaldate($event['endtime'])."\r\n";
	echo "URL;VALUE=URI:https://news.piratenpartei.de/calendar.php?action=event&eid=".$event['eid']."\r\n";
	//echo "LOCATION:Neue Str. 58 Eingang Lämmertwiete\r\n";
	echo "DESCRIPTION:".$event['description']."\r\n";
	//echo "ignoretimezone ".$event['ignoretimezone']."\r\n";
	//echo "usingtime ".$event['usingtime']."\r\n";
	//echo "repeats ".$event['repeats']."\r\n";
	//echo "import_eid ".$event['import_eid']."\r\n";
	//echo "timezone ".$event['timezone']."\r\n";
	//echo "ORGANIZER;CN="Alice Balder, Example Inc.":MAILTO:alice@example.com/n";
	//echo "CLASS:PUBLIC\r\n";
	echo "END:VEVENT\r\n";
}
echo "END:VCALENDAR\r\n";

// To-Do:
// - Zeitzone
// - Wiederholende Termine
// - Titel - über Kalender?
// - Beschreibung escapen
// - BBCode-Wandlung
// - Parameter für User und Kalender
// - Location hinzufügen
?>

<?php
define("IN_MYBB", 1);
//define('THIS_SCRIPT', 'ical.php');

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../global.php";

require_once MYBB_ROOT."inc/functions_calendar.php";
require_once MYBB_ROOT."inc/class_parser.php";

//$mybb->settings['dstcorrection'] = 0;
//$mybb->settings['timezoneoffset'] = 0;

// 'dst'
//print_r($mybb->settings);
//die();

function icaldate($date, $strday = '') {
	if ($strday == '')
		$strday = date("Ymd", $date);

	// To-Do: Richtige Zeitzone raussuchen
	date_default_timezone_set("Europe/Berlin");

	// To-Do: richtige Zeitzonendifferenz heraussuchen
	if (date('I', strtotime($strday)) == 1)
		$date = $date - 3600;

	date_default_timezone_set("UTC");

	return($strday."T".date("His", $date)."Z");
}

header('Cache-Control: store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Expires: Sun, 19 Nov 1978 05:00:00 GMT');
header('Content-Disposition: attachment; filename="calendar.ics";');
header("Content-Type: text/calendar; charset=utf-8");

//die(date('IeO'));

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:https://news.piratenpartei.de/calendar.php\r\n";
//echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME: Piratenkalender\r\n";
//echo "X-WR-TIMEZONE:UTC\r\n";

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

// $events_cache = get_events($calendar['cid'], $start_timestamp, $end_timestamp, $calendar_permissions['canmoderateevents']);
$events = get_events(2, 1, 11111111111, false);

//print_r($events);
//die();

foreach ($events as $day=>$events2) {
	// in $day steht der Wochentag
	$dayarr = explode('-', $day);
	$dateday = strtotime($dayarr[0].".".$dayarr[1].".".$dayarr[2]);
	$strdate = date("Ymd", $dateday);

	$nextday = $dateday + 86500;
	$strnext = date("Ymd", $nextday);

   foreach ($events2 as $event) {
	$line = "BEGIN:VEVENT\r\n";
	// $line .= "CATEGORIES:Stammtisch\r\n";
	$line .= "CLASS:PUBLIC\n\r";
	$line .= "UID:".$day."-".$event['eid']."-".$event['cid']."-".$event['uid']."\r\n";
	// ORGANIZER;CN="Berlin Landesverband":MAILTO:kalender@piratenpartei-bayern.de
	$line .= "SUMMARY:".addcslashes(trim($event['name']), ",;\n".'')."\r\n";

	$parser = new postParser;

	$parser_options = array(
			'allow_html' => 0,
			'filter_badwords' => 1,
			'allow_mycode' => 1,
			'allow_smilies' => 0,
			'allow_imgcode' => 0,
			"filter_badwords" => 1
			);
        $parsed = $parser->parse_message($event['description'], $parser_options);

	$line .= "DESCRIPTION:".addcslashes(trim(str_replace("\n","\n ",html_entity_decode(strip_tags($parsed)))), ",;".'"')."\r\n";

	//$line .= "LOCATION:Neue Str. 58 Eingang Lämmertwiete\r\n";
	if ($event['endtime'] == 0) {
		$line .= "DTSTART;VALUE=DATE:".$strdate."\n\r";
		$line .= "DTEND;VALUE=DATE:".$strnext."\n\r";
	} else {
		//$line .= "DTSTART;TZID=Europe/Berlin:".icaldate($event['starttime'], $strdate)."\r\n";
		//$line .= "DTEND;TZID=Europe/Berlin:".icaldate($event['endtime'], $strdate)."\r\n";
		$line .= "DTSTART:".icaldate($event['starttime'], $strdate)."\r\n";
		$line .= "DTEND:".icaldate($event['endtime'], $strdate)."\r\n";
	}
	$line .= "DTSTAMP:".icaldate($event['dateline'])."\r\n";
	$line .= "URL;VALUE=URI:https://news.piratenpartei.de/calendar.php?action=event&eid=".$event['eid']."\r\n";

	//echo "ignoretimezone ".$event['ignoretimezone']."\r\n";
	//echo "usingtime ".$event['usingtime']."\r\n";
	//echo "repeats ".$event['repeats']."\r\n";
	//echo "import_eid ".$event['import_eid']."\r\n";
	//echo "timezone ".$event['timezone']."\r\n";
	$line .= "END:VEVENT\r\n";
	$line = str_replace("\r", "", $line);
	$line = str_replace("\n", "\r\n", $line);
	echo $line;
   }
}
echo "END:VCALENDAR\r\n";

// To-Do:
// - Zeitzone konfigurieren
// - sinnvoller Zeitraum
// - Titel - über Kalender?
// - Parameter für User und Kalender
// - Location hinzufügen
?>

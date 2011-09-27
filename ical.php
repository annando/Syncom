<?php
define("IN_MYBB", 1);

$basepath = dirname($_SERVER["SCRIPT_FILENAME"]);

require_once $basepath."/../global.php";

require_once MYBB_ROOT."inc/functions_calendar.php";
require_once MYBB_ROOT."inc/class_parser.php";

function icaldate($date, $strday = '') {
	if ($strday == '')
		$strday = date("Ymd", $date);

	// To-Do: Richtige Zeitzone raussuchen
	date_default_timezone_set("Europe/Berlin");

	//        Pruefung auf Sommerzeit muss die Stunde beinhalten
	if (date('I', strtotime($strday.' '.date("H:i:s", $date))) == 1)
		$date = $date - 3600;

	date_default_timezone_set("UTC");

	return($strday."T".date("His", $date)."Z");
}

header('Cache-Control: store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Expires: Sun, 19 Nov 1978 05:00:00 GMT');
header('Content-Disposition: attachment; filename="calendar.ics";');
header("Content-Type: text/calendar; charset=utf-8");

$calendar = (int)$_REQUEST["calendar"];

// Zeitzone auf UTC stellen, damit die Kalenderdaten nicht umgerechnet werden
date_default_timezone_set("UTC");

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:https://news.piratenpartei.de/calendar.php\r\n";
echo "METHOD:PUBLISH\r\n";
// To-Do: Name aus Kalender uebernehmen
echo "X-WR-CALNAME: Piratenkalender\r\n";

$events = get_events($calendar, time()-(86400*30), time()+(86400*90), false);

foreach ($events as $day=>$events2) {
	// in $day steht der Wochentag
	$dayarr = explode('-', $day);
	$dateday = strtotime($dayarr[0].".".$dayarr[1].".".$dayarr[2]);
	$strdate = date("Ymd", $dateday);

	$nextday = $dateday + 86500;
	$strnext = date("Ymd", $nextday);

	foreach ($events2 as $event) {
		$line = "BEGIN:VEVENT\r\n";
		$line .= "CLASS:PUBLIC\n\r";
		$line .= "UID:".$day."-".$event['eid']."-".$event['cid']."-".$event['uid']."\r\n";
		$line .= "SUMMARY:".addcslashes(trim($event['name']), ",;\n".'')."\r\n";

		$parser = new postParser;

		$parser_options = array(
				'allow_html' => 0,
				'filter_badwords' => 1,
				'allow_mycode' => 1,
				'allow_smilies' => 0,
				'allow_imgcode' => 0
				);
	        $parsed = $parser->parse_message($event['description'], $parser_options);

		$line .= "DESCRIPTION:".addcslashes(trim(html_entity_decode(strip_tags($parsed))), ",;\n")."\r\n";

		// To-Do: Location fuellen, benotigt aber neues Feld in DB
		// $line .= "LOCATION:Neue Str. 58 Eingang Lämmertwiete\r\n";

		if (!$event['usingtime']) {
			$line .= "DTSTART;VALUE=DATE:".$strdate."\n\r";
			$line .= "DTEND;VALUE=DATE:".$strnext."\n\r";
		} else {
			$line .= "DTSTART:".icaldate($event['starttime'], $strdate)."\r\n";
			$line .= "DTEND:".icaldate($event['endtime'], $strdate)."\r\n";
		}
		$line .= "DTSTAMP:".icaldate($event['dateline'])."\r\n";

		// To-Do: URL muss noch dynamisch sein
		$line .= "URL;VALUE=URI:https://news.piratenpartei.de/calendar.php?action=event&eid=".$event['eid']."\r\n";

		$line .= "END:VEVENT\r\n";
		$line = str_replace("\r", "", $line);
		$line = str_replace("\n", "\r\n", $line);
		echo $line;
	}
}
echo "END:VCALENDAR\r\n";
?>

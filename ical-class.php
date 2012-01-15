<?php
//class for export sheduler events in icalendar format
class ICalExporter {
	private $title; // calendar view title

	//set name the calendar
	function setTitle($t) {
		$this->title = $t;
	}

	//get calendar name
	function getTitle() {
		return $this->title;
	}

	//returns the string value of the day instead of its ordinal number or return number
	function getConvertDay($i, $mode=false) {
		//return(trim($i));
		$i = trim($i);
		//return($i);

		$a = array ("SU","MO","TU","WE","TH","FR","SA");
		if($mode) {
			for($y=0;$y<sizeof($a);$y++){
				if($a[$y] == $i) {
					return $y;
				}
			}
		}
		else{
			return $a[$i];
		}
	}

	//returns the appropriate line
	function getConvertType($i, $mode=false) {
		$i = trim($i);
		//return(trim($i));

		$a = array ('1' => "DAILY",'3' => "WEEKLY",'4' => "MONTHLY",'5' => "YEARLY");
		if($mode) {
			foreach ($a as $key => $value) {
				if($a[$key] == $i) {
					return $key;
				}
			}
		}
		else {
			return $a[$i];
		}
	}

	//returns the strings value of the days instead of its ordinal numbers
	function getConvertDays($n, $ind=false) {
		$a = explode(",", $n);
		$str = "";
		for($i=0;$i<sizeof($a);$i++) {
			$str .=  $this->getConvertDay($a[$i]);
			if($i != sizeof($a)-1) { $str .= ","; }
		}
		return $str;
	}

	//give date in ical format and return date in MySQL format
	function getMySQLDate($str, $tz) {

		$sqldate = '';

		preg_match('/[0-9]{8}[T][0-9]{6}/',trim($str),$date);
		if(isset($date[0])) {
			if($date[0] != "") {
				$y = substr($date[0], 0, 4);
				$mn = substr($date[0], 4, 2);
				$d = substr($date[0], 6, 2);
				$h = substr($date[0], 9, 2);
				$m = substr($date[0], 11, 2);
				$s = substr($date[0], 13, 2);
				$sqldate = $y."-".$mn."-".$d." ".$h.":".$m.":".$s;
			}
		}
		elseif(strlen(trim($str)) == 8) {
			$y = substr($str, 0, 4);
			$mn = substr($str, 4, 2);
			$d = substr($str, 6, 2);
			$sqldate = $y."-".$mn."-".$d." 00:00:00";
		}

		if (substr($tz, 0, 5) == 'TZID=') {
			$tz = substr($tz, 5);
			date_default_timezone_set($tz);
		} else if ($tz == 'VALUE=DATE') {
			$y = substr($str, 0, 4);
			$mn = substr($str, 4, 2);
			$d = substr($str, 6, 2);
			return($y."-".$mn."-".$d);
		} else if (substr(trim($str), -1) != "Z")
			date_default_timezone_set("Europe/Berlin");
		else
			date_default_timezone_set("UTC");

		$date = strtotime($sqldate);

		// Rausrechnen der Zeitzone - eher eklig, da feste Verdrahtung
		date_default_timezone_set("Europe/Berlin");
		$strday = date("Ymd", $date);
		if (date('I', strtotime($strday.' '.date("H:i:s", $date))) == 1)
			$date = $date + 3600;
		date_default_timezone_set("UTC");

		/*if ($tz != '') {
			echo $sqldate." - ".$tz."\n";
			echo gmdate("Y-m-d H:i:s", $date)."\n";
			echo date("Y-m-d H:i:s", $date)."\n";
			echo "-----------------\n";
		}*/

		return(gmdate("Y-m-d H:i:s", $date));
	}

	//get parse a string into an array
	function getParseString($str) {
		$arr_n = array();
		$arr = explode("BEGIN:VEVENT",$str);
		$addfield = '';
		$inFlowtext = false;

		for($x=1;$x<sizeof($arr);$x++) {

			$arr_n[$x]['location'] = ''; // Syncom
			$arr_n[$x]['last-modified'] = ''; // Syncom
			$arr_n[$x]['url'] = ''; // Syncom
			$arr_n[$x]['dtstamp'] = ''; // Syncom
			$arr_n[$x]['created'] = ''; // Syncom

			$arr2 = explode("\n",$arr[$x]);
			for($y=1;$y<sizeof($arr2);$y++) {
				$mas = explode(":",$arr2[$y]);
				$mas_ = explode(";",$mas[0]);
				if(isset($mas_[0]))
					$mas[0] = $mas_[0];

				if (sizeof($mas_) < 2)
					$mas_ = array($mas[0], '');

				switch(trim($mas[0])) {
					case "DTSTART":
						$arr_n[$x]['start_date'] = $this->getMySQLDate($mas[1], $mas_[1]);
						break;

					case "DTEND":
						$arr_n[$x]['end_date'] = $this->getMySQLDate($mas[1], $mas_[1]);
						break;

					case "RRULE":
						$rrule = explode(";", $mas[1]);
						for($z=0;$z<sizeof($rrule);$z++) {
							$rrule_n = explode("=", $rrule[$z]);
							switch($rrule_n[0]) {
								case "FREQ":
									$arr_n[$x]['type'] = $this->getConvertType($rrule_n[1], true);
									break;

								case "INTERVAL":
									$arr_n[$x]['count'] = $rrule_n[1];
									break;

								case "COUNT":
									$arr_n[$x]['extra'] = $rrule_n[1];
									break;

								case "BYDAY":
									$bayday = explode(",",$rrule_n[1]);
									if(sizeof($bayday) == 1) {
										if(strlen(trim($bayday[0])) == 3) {
											$arr_n[$x]['day'] = substr($bayday[0], 0, 1);
											$arr_n[$x]['counts'] = $this->getConvertDay(substr($bayday[0], 1, 2), true);
										}
										else {
											$arr_n[$x]['days'] = $this->getConvertDay($bayday[0], true);
										}
									}
									else {
										$arr_n[$x]['days'] = "";
										for($nx=0;$nx<sizeof($bayday);$nx++) {
											$arr_n[$x]['days'] .= $this->getConvertDay($bayday[$nx], true);
											if($nx != sizeof($bayday)-1) {
												$arr_n[$x]['days'] .= ",";
											}
										}
									}
									break;

								case "UNTIL":
									$arr_n[$x]['until'] = $this->getMySQLDate($rrule_n[1], $mas_[1]);
									break;
							}
						}
						break;

					case "EXDATE":
						$exdate = explode(",",trim($mas[1]));
						if(sizeof($exdate) == 1) {
							$arr_n[$x]['exdate'] = $this->getMySQLDate($exdate[0], $mas_[1]);
						}
						else {
							for($nx=0;$nx<sizeof($exdate);$nx++) {
								$arr_n[$x]['exdate'][$nx] = $this->getMySQLDate($exdate[$nx], $mas_[1]);
							}
						}
						break;

					case "RECURRENCE-ID":
						$arr_n[$x]['rec_id'] = $this->getMySQLDate($mas[1], $mas_[1]);
						break;

					case "UID":
						//$arr_n[$x]['event_id'] = $x;
						$arr_n[$x]['event_id'] = trim($mas[1]);
						$arr_n[$x]['uid'] = trim($mas[1]); // Syncom
						break;

					// Syncom - Start
					case "DESCRIPTION":
						//$arr_n[$x]['description'] = trim($mas[1]);
						$arr_n[$x]['description'] = trim(substr($arr2[$y], 12));
						$addfield = 'description';
						break;

					case "LOCATION":
						$arr_n[$x]['location'] = trim($mas[1]);
						$addfield = 'location';
						break;

					case "LAST-MODIFIED":
						$arr_n[$x]['last-modified'] = $this->getMySQLDate($mas[1], $mas_[1]);
						break;

					case "SEQUENCE":
						$arr_n[$x]['sequence'] = trim($mas[1]);
						break;

					case "STATUS":
						$arr_n[$x]['status'] = trim($mas[1]);
						break;

					case "TRANSP":
						$arr_n[$x]['transp'] = trim($mas[1]);
						break;

					case "URL":
						$arr_n[$x]['url'] = trim(substr($arr2[$y], 4));
						break;

					case "DTSTAMP":
						$arr_n[$x]['dtstamp'] = $this->getMySQLDate($mas[1], $mas_[1]);
						break;

					case "CREATED":
						$arr_n[$x]['created'] = $this->getMySQLDate($mas[1], $mas_[1]);
						break;

					case "END":
						break;

					case "X-APPLE-NEEDS-REPLY":
						$arr_n[$x]['x-apple-needs-reply'] = trim($mas[1]);
						break;
					// Syncom - End

					case "SUMMARY":
						//$arr_n[$x]['text'] = trim($mas[1]);
						$arr_n[$x]['text'] = trim(substr($arr2[$y], 8));
						$addfield = 'text';
						break;

					default: // Syncom
						//print_r($mas);
						//echo $arr2[$y]."\n";
						//die('');
						$inFlowtext = true; // Syncom
						if (($addfield != '') and (substr($arr2[$y], 0, 1) == ' ')) // Syncom
							@$arr_n[$x][$addfield] .= substr(str_replace(array("\r"), array(''), $arr2[$y]), 1); // Syncom

						break; // Syncom
				}
			}
			if (!$inFlowtext) // Syncom
				$addfield = ''; // Syncom

			if(isset($arr_n[$x]['rec_id'])){
				$arr_n[$x]['event_pid'] = $arr_n[$x]['event_id'];
			}
			if(isset($arr_n[$x]['exdate'])){
				$arr_n[$x]['event_pid'] = $arr_n[$x]['event_id'];
			}
		}
		return $arr_n;
	}

	function getNativeStartDate($arr_p) {
		/*if(isset($arr_p['day']) or isset($arr_p['days'])) {
			$odate = strtotime($arr_p['start_date']);

			switch($arr_p['type']) {
				case 1:
					$week_day = (date("N",$odate)-1)*60*60*24;
					$week_start = date("Y-m-d H:i:s", $odate - $week_day);
					$start_date = $week_start;
					break;

				case 3:
				case 4:
					$start_date = date("Y-m", $odate)."-01 ".date("H:i:s", $odate);
					break;
			}
		}
		else {*/
			$start_date = $arr_p['start_date'];
		//}
		return $start_date;
	}

	function getSortArrayById($arr) {
		$id = 1;
		for($x=1;$x<=sizeof($arr);$x++){
			for($y=1;$y<=sizeof($arr);$y++){
				if($arr[$x]['event_id'] == $arr[$y]['event_pid'] and $arr[$x]['event_pid'] == "0" and $arr[$y]['event_pid'] != "0"){
					if($arr[$y]['rec_type'] == "" or $arr[$y]['rec_type'] == "none") {
						$arr[$y]['event_pid'] = $id;
					}
				}
			}
			$arr[$x]['event_id'] = $id;
			$id++;
		}
		return $arr;
	}

	//return hashs
	function toHash($str) {
		if(strpos($str, "BEGIN:VCALENDAR") === false) {
			$str = file_get_contents($str);
		}
		$arr_p = $this->getParseString($str);
		$arr_n = array();
		$id = 1;
		for($i=1;$i<=sizeof($arr_p);$i++) {
			if(isset($arr_p[$i]['rec_id'])){
				$arr_n[$i]['uid'] = $arr_p[$i]['uid']; // Syncom
				$arr_n[$i]['location'] = $arr_p[$i]['location']; // Syncom
				$arr_n[$i]['last-modified'] = $arr_p[$i]['last-modified']; // Syncom
				$arr_n[$i]['url'] = $arr_p[$i]['url']; // Syncom
				$arr_n[$i]['dtstamp'] = $arr_p[$i]['dtstamp']; // Syncom
				$arr_n[$i]['created'] = $arr_p[$i]['created']; // Syncom
				$arr_n[$i]['event_id'] = $arr_p[$i]['event_id'];
				$arr_n[$i]['start_date'] = $arr_p[$i]['start_date'];
				$arr_n[$i]['end_date'] = $arr_p[$i]['end_date'];
				$arr_n[$i]['text'] = $arr_p[$i]['text'];
				$arr_n[$i]['description'] = $arr_p[$i]['description']; // Syncom
				$arr_n[$i]['rec_type'] = "";
				$arr_n[$i]['repeats'] = serialize(array("repeats" => 0));

				$arr_n[$i]['event_pid'] = $arr_p[$i]['event_pid'];
				$arr_n[$i]['event_length'] = strtotime($arr_p[$i]['rec_id']);
			}
			else {
				if(isset($arr_p[$i]['exdate'])){
					if(sizeof($arr_p[$i]['exdate'])> 1) {
						for($ni=0;$ni<sizeof($arr_p[$i]['exdate']);$ni++) {
							$arr_n[sizeof($arr_p)+$id]['uid'] = $arr_p[$i]['uid']; // Syncom
							$arr_n[sizeof($arr_p)+$id]['event_id'] = $arr_p[$i]['event_id'];
							$arr_n[sizeof($arr_p)+$id]['start_date'] = $arr_p[$i]['exdate'][$ni];
							$arr_n[sizeof($arr_p)+$id]['end_date'] = date("Y-m-d H:i:s", strtotime($arr_p[$i]['exdate'][$ni])
								+(strtotime($arr_p[$i]['end_date']) - strtotime($arr_p[$i]['start_date'])));
							$arr_n[sizeof($arr_p)+$id]['text'] = "";
							$arr_n[sizeof($arr_p)+$id]['description'] = "";
							$arr_n[sizeof($arr_p)+$id]['rec_type'] = "none";
							$arr_n[sizeof($arr_p)+$id]['event_pid'] = $arr_p[$i]['event_pid'];
							$arr_n[sizeof($arr_p)+$id]['event_length'] = strtotime($arr_p[$i]['exdate'][$ni]);
							$id++;
						}
					}
					else {
							$arr_n[sizeof($arr_p)+$id]['uid'] = $arr_p[$i]['uid']; // Syncom
							$arr_n[sizeof($arr_p)+$id]['event_id'] = $arr_p[$i]['event_id'];
							$arr_n[sizeof($arr_p)+$id]['start_date'] = $arr_p[$i]['exdate'];
							$arr_n[sizeof($arr_p)+$id]['end_date'] = date("Y-m-d H:i:s", strtotime($arr_p[$i]['exdate'])
								+(strtotime($arr_p[$i]['end_date']) - strtotime($arr_p[$i]['start_date'])));
							$arr_n[sizeof($arr_p)+$id]['text'] = "";
							$arr_n[sizeof($arr_p)+$id]['description'] = "";
							$arr_n[sizeof($arr_p)+$id]['rec_type'] = "none";
							$arr_n[sizeof($arr_p)+$id]['event_pid'] = $arr_p[$i]['event_pid'];
							$arr_n[sizeof($arr_p)+$id]['event_length'] = strtotime($arr_p[$i]['exdate']);
							$id++;
					}
				}
				//id
				$arr_n[$i]['event_id'] = $arr_p[$i]['event_id'];

				//uid
				$arr_n[$i]['uid'] = $arr_p[$i]['uid']; // Syncom

				//start_date
				$arr_n[$i]['start_date'] = $this->getNativeStartDate($arr_p[$i]);

				//rec_type
				isset($arr_p[$i]['type'])? $type = $arr_p[$i]['type'] : $type = "";
				isset($arr_p[$i]['count'])? $count = $arr_p[$i]['count'] : $count = "";
				isset($arr_p[$i]['counts'])? $counts = $arr_p[$i]['counts'] : $counts = "";
				isset($arr_p[$i]['day'])? $day = $arr_p[$i]['day'] : $day = "";
				isset($arr_p[$i]['days'])? $days = $arr_p[$i]['days'] : $days = "";
				isset($arr_p[$i]['extra'])? $extra = $arr_p[$i]['extra'] : $extra = "no";
				if($type != "" and $count == "") {
					$count = 1;
				}
				if($type != "") {
					$arr_n[$i]['rec_type'] = $type."_".$count."_".$counts."_".$day."_".$days."#".$extra;
				}
				else {
					$arr_n[$i]['rec_type'] = "";
				}

				$counttype = array(1=>"days", 3=>"weeks", 4=>"months", 5=>"years");
				$repeats = array();
				$repeats["repeats"] = (int)$type;
				$repeats[$counttype[$type]] = (int)$count;

				if ($counts != '')
					$repeats["occurance"] = (int)$day;

				if ($counts != '')
					$repeats["weekday"] = (int)$counts;

				if ($days != '') {
					$days = explode(",", $days);

					$repeats["days"] = array();

					foreach($days as $day)
						$repeats["days"][] = (int)$day;
				} else if ($repeats["repeats"] == 3) {
					//die('days');
					$repeats["days"] = array((int)date('N', strtotime($arr_n[$i]['start_date'])));
				}

				if (($repeats["repeats"] == 4) and (sizeof($repeats) < 4)) {
					$repeats = array("repeats" => -1);
				}

				$arr_n[$i]['repeats'] = serialize($repeats);

				//end_date
				if(isset($arr_p[$i]['until'])){
					$arr_n[$i]['end_date'] = $arr_p[$i]['until'];
				}
				else {
					if($arr_n[$i]['rec_type'] == "") {
						if(isset($arr_p[$i]['end_date'])) {
							$arr_n[$i]['end_date'] = $arr_p[$i]['end_date'];
						}
						else {
							$arr_n[$i]['end_date'] =  date("Y-m-d H:i:s",strtotime($arr_n[$i]['start_date'])+24*60*60);
							$arr_p[$i]['end_date'] = $arr_n[$i]['end_date'];
						}
					}
					else {
						if ($extra == "no") {
							//$arr_n[$i]['end_date'] = "9999-02-01 00:00:00";
							$arr_n[$i]['end_date'] = "2099-12-31".substr($arr_p[$i]['end_date'], 10);
						} else {
							$date = strtotime($arr_p[$i]['start_date']);

							// To-Do:
							// Regel fuer mengenmaeßig begrenzte Termine (count)
							// erstmal nur sehr billig gemacht
							switch ($repeats["repeats"]) {
								case 1: $date = $date + ($count*--$extra*86400);
									break;
								case 3: $date = $date + ($count*--$extra*86400*7);
									break;
								case 4: $date = $date + ($count*--$extra*86400*31);
									break;
								case 5: $date = $date + ($count*--$extra*86400*366);
									break;
							}
							$end_date = gmdate("Y-m-d H:i:s", $date);

							$arr_n[$i]['end_date'] = $end_date;
							//$arr_n[$i]['end_date'] = $arr_p[$i]['end_date'];
						}
					}
				}
				//text
				$arr_n[$i]['text'] = $arr_p[$i]['text'];

				// Syncom-Start
				//Description
				$arr_n[$i]['description'] = $arr_p[$i]['description'];
				$arr_n[$i]['location'] = $arr_p[$i]['location']; // Syncom
				$arr_n[$i]['last-modified'] = $arr_p[$i]['last-modified']; // Syncom
				$arr_n[$i]['url'] = $arr_p[$i]['url']; // Syncom
				$arr_n[$i]['dtstamp'] = $arr_p[$i]['dtstamp']; // Syncom
				$arr_n[$i]['created'] = $arr_p[$i]['created']; // Syncom
				// Syncom-End


				//event_pid
				$arr_n[$i]['event_pid'] = "0";

				//event_length
				$arr_n[$i]['event_length'] = strtotime($arr_p[$i]['end_date']) - strtotime($arr_p[$i]['start_date']);
			}
		}
		return $this->getSortArrayById($arr_n);
	}
}
?>

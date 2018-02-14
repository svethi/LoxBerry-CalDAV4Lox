<?php

/*
* @author    Sven Thierfelder & Christian Fenzl
* @copyright SD-Thierfelder & Christian Fenzl
*/

$timestart = microtime(true);
header('Content-Type: text/html; charset=utf-8');

require_once("class_caldav.php");
require __DIR__ . '/vendor/autoload.php';

use Recurr\Rule;

date_default_timezone_set(date("e"));

$calURL = @($_GET["calURL"]);
$user = @($_GET["user"]);
$pass = @($_GET["pass"]);
$fwdays = @($_GET["fwdays"]);
$sevents = @explode("|",$_GET["events"]);
$search = @($_GET["events"]);
$delay = @($_GET["delay"]);
$debug = @($_GET["debug"]);
$cache = @($_GET["cache"]);

$home = posix_getpwuid(posix_getuid());
$home = $home['dir'];

# Figure out in which subfolder we are installed

$psubfolder = __FILE__;
$psubfolder = preg_replace('/(.*)\/(.*)\/(.*)$/',"$2", $psubfolder);

$myFile = "$home/data/plugins/$psubfolder/caldav_".MD5($calURL).".ical";

//Get depth from conffile
$caldavconf = parse_ini_file("$home/config/plugins/$psubfolder/caldav4lox.conf");

$depth = $caldavconf['Depth'];

if (!isset($delay)) $delay = 60;

foreach ( $sevents AS $e => $event ) {
        $results[$event] = array("Start" => -1, "End" => -1, "Summary" => "", "Desc" => "", "fwDay" => -1, "wkDay" => -1);
}

$ustart = time()-($delay * 60);
$vdstart = mktime(0,0,0,date("m",$ustart),date("d",$ustart),date("Y",$ustart));
$start = gmdate("Ymd\THis\Z",$ustart);
$uend = mktime(date("H"),date("i")+$delay,date("s"),date("m"),date("d")+$fwdays,date("Y"));
$end = gmdate("Ymd\THis\Z",$uend);

$datediff = mktime(0,0,0,1,1,2009);
$localTZ = new DateTimeZone(date("e"));

$timeend = microtime(true) - $timestart;
//echo "$timeend - Start Kalenderabholung\n";

if (preg_match("/google\.com\/calendar/",$calURL)) {
	//Google Kalender
	//echo "Google Kalender erkannt\n";
	$events = array();
	if( $cache > 0 ) {
		if(filemtime($myFile) < date("U")-($cache * 60)) {
			//echo "Cachefile abgelaufen";
$timeend = microtime(true) - $timestart;
//echo "$timeend - Lade Kalender von Google";
			$fh = fopen($myFile, 'w') or die("can't open file");
			$context = stream_context_create(array(
				'https' => array(
					'header'  => "Authorization: Basic " . base64_encode("$user:$pass"))
			));

			set_error_handler(
    		create_function(
        	'$severity, $message, $file, $line',
        	'throw new ErrorException($message, $severity, $severity, $file, $line);'
   	 		)
			);
			try {
				$Datei = file_get_contents($calURL, false, $context);
			}
			catch (Exception $e) {
    		echo $e->getMessage();
			}

			restore_error_handler();
			fwrite($fh, $Datei);
			fclose($fh);
$timeend = microtime(true) - $timestart;
//echo "$timeend - Laden des Kalenders beendet.";
		} else {
			$timeend = microtime(true) - $timestart;
			//echo "$timeend - Lade Cachefile";
			set_error_handler(
    		create_function(
        	'$severity, $message, $file, $line',
        	'throw new ErrorException($message, $severity, $severity, $file, $line);'
   	 		)
			);
			try {
				$Datei = file_get_contents($myFile);
			}
			catch (Exception $e) {
    		echo $e->getMessage();
			}

			restore_error_handler();
			$timeend = microtime(true) - $timestart;
			//echo "$timeend - Cachefile geladen.";
		}
	} else {
		$context = stream_context_create(array(
			'https' => array(
				'header'  => "Authorization: Basic " . base64_encode("$user:$pass"))
		));
			set_error_handler(
    		create_function(
        	'$severity, $message, $file, $line',
        	'throw new ErrorException($message, $severity, $severity, $file, $line);'
   	 		)
			);
			try {
				$Datei = file_get_contents($calURL, false, $context);
			}
			catch (Exception $e) {
    		echo $e->getMessage();
			}

			restore_error_handler();
	}
	$timeend = microtime(true) - $timestart;
	//echo "$timeend - Beginne mit Eintragssplitting\n";
	preg_match_all("/(BEGIN:VEVENT.*END:VEVENT)/isU",$Datei,$gevents, PREG_PATTERN_ORDER);
	foreach ( $gevents[1] AS $e => $event ) {
		if (preg_match("/SUMMARY:(.*($search)[^\r\n]*)/",$event,$ematch)) { 
		$countevents +=1;

		$teststart = $ustart;
		if(preg_match("/DTSTART;.*TZID=(.*);(VALUE=DATE):(.*)\b/",$event,$estart)) {
			$teststart = $vdstart;
			$estart[3] .= "T000000";
			$estart = DateTime::createFromFormat('YmdHis',str_replace("T","",$estart[3]),new DateTimeZone($estart[1]));
		} elseif (preg_match("/DTSTART;.*TZID=(.*):(.*)\b/",$event,$estart)) {
			$estart = DateTime::createFromFormat('YmdHis',str_replace("T","",$estart[2]));
		} elseif (preg_match("/DTSTART(:)(.*)Z\b/",$event,$estart)) {
			$estart = DateTime::createFromFormat('YmdHis',str_replace("T","",$estart[2]),new DateTimeZone("UTC"));
		} elseif (preg_match("/DTSTART;(VALUE=DATE):(.*)\b/",$event,$estart)) {
			$teststart = $vdstart;
			$estart[2] .= "T000000";
			$estart = DateTime::createFromFormat('YmdHis',str_replace("T","",$estart[2]));
		}
		if (preg_match("/DTEND;.*TZID=(.*);(VALUE=DATE):(.*)\b/",$event,$eend)) {
			$eend[3] .= "T000000";
			$eend = DateTime::createFromFormat('YmdHis',str_replace("T","",$eend[3]),new DateTimeZone($eend[1]));
		} elseif (preg_match("/DTEND;.*TZID=(.*):(.*)\b/",$event,$eend)) {
			$eend = DateTime::createFromFormat('YmdHis',str_replace("T","",$eend[2]),new DateTimeZone($eend[1]));
		} elseif (preg_match("/DTEND(:)(.*)Z\b/",$event,$eend)) {
			$eend = DateTime::createFromFormat('YmdHis',str_replace("T","",$eend[2]),new DateTimeZone("UTC"));
		} elseif (preg_match("/DTEND;(VALUE=DATE):(.*)\b/",$event,$eend)) {
			$eend[2] .= "T000000";
			$eend = DateTime::createFromFormat('YmdHis',str_replace("T","",$eend[2]));
		}
		date_timezone_set($estart,$localTZ);
		date_timezone_set($eend,$localTZ);

		$diff = date_format($eend,"U") - date_format($estart,"U");
		if (preg_match("/RRULE:(.*)/",$event,$rrule)) {
			//Wiederholungen testen
			$timeend = microtime(true) - $timestart;
			//echo "$timeend - Wiederholung gefunden, RRULE starten.\n";
			preg_match_all("/EXDATE.*:(.*)\s/iU",$event,$resExDates, PREG_PATTERN_ORDER);
			foreach ($resExDates[1] AS $d => $ExDate) {
				$ExDates[] = $ExDate;
			}
			if (preg_match("/RDATE.*:(.*)\s/",$event,$resRDates)) {
				$RDates = explode(",",$resRDates[1]);
			}
			$recurr = new \Recurr\Rule;
			$recurr->loadFromString(trim($rrule[1]));
			$recurr->setStartDate($estart);
			if (isset($ExDates)) $recurr->setExDates($ExDates);
			if (isset($RDates)) $recurr->setRDates($RDates);
			//$constraint = new \Recurr\Transformer\Constraint\BetweenConstraint(DateTime::createFromFormat("U",$teststart), DateTime::createFromFormat("U",$uend),True);
			$transformer = new \Recurr\Transformer\ArrayTransformer();
			$recresult = $transformer->transform($recurr,$constraint);
			$recresult = $recresult->startsAfter(DateTime::createFromFormat("U",$teststart),true);
			$iterator = $recresult->getIterator();
			$iterator->uasort(function ($a, $b) {
		   	 return ($a->getStart() < $b->getStart()) ? -1 : 1;
			});
			$recresult = new \Recurr\RecurrenceCollection(iterator_to_array($iterator));
			if (isset($recresult)) {
				$fdate = $recresult->first();
				if ($fdate) $date=$fdate->getStart();
			}
			if (isset($date) && $uend>=$date->getTimestamp()) {
				$event = preg_replace("/RRULE:.*[\n]/","",$event);
				$event = preg_replace("/DTSTART.*/","DTSTART;TZID=".date("e").":".date_format($date,"Ymd\THis"),$event);
				$event = preg_replace("/DTEND.*/","DTEND;TZID=".date("e").":".date("Ymd\THis",$date->getTimestamp() + $diff),$event);
				$events[]['data']=$event;
			}
			$timeend = microtime(true) - $timestart;
			//echo "$timeend - RRULE ausgeführt\n";
		} elseif ( date_format($eend, "U") >= $ustart  && date_format($estart, "U") <= $uend ) {
                        $events[]['data']=$event;
		}
		}
	}
} else {
	set_error_handler(
  	create_function(
     	'$severity, $message, $file, $line',
     	'throw new ErrorException($message, $severity, $severity, $file, $line);'
  	)
	);
	try {
		$cal = new CalDAVClient( $calURL, $user, $pass, "" );
		$options = $cal->DoOptionsRequest();
		if ( isset($options["PROPFIND"]) ) {
			// Fetch some information about the events in that calendar
			$cal->SetDepth($depth);
			$folder_xml = $cal->DoXMLRequest("PROPFIND", '<?xml version="1.0" encoding="utf-8" ?><propfind xmlns="DAV:"><prop><getcontentlength/><getcontenttype/><resourcetype/><getetag/></prop></propfind>' );
		}
		$cal->SetDepth($depth);
		$events = $cal->GetEvents($start,$end);
	}
	catch (Exception $e) {
  		echo $e->getMessage();
	}
	restore_error_handler();
}
//print "$start:$end\n";
//print_r($events);
foreach ( $events AS $k => $event ) {
	$ematch = "";
	$estart = "";
	$teststart = $ustart;
	preg_match("/(BEGIN:VEVENT.*END:VEVENT)/s",$event['data'],$tmp);
//print_r($tmp);
	$event['data'] = $tmp[1];
	$event['data'] = preg_replace("/BEGIN:VALARM.*END:VALARM/s","",$event['data']);
	if (preg_match("/SUMMARY:(.*($search)[^\r\n]*)/",$event['data'],$ematch)) {
                if(preg_match("/DTSTART;.*TZID=(.*);(VALUE=DATE):(.*)\b/",$event['data'],$estart)) {
			$teststart = $vdstart;
			$estart[3] .= "T000000";
                        $tmpstart = DateTime::createFromFormat('YmdHis',str_replace("T","",$estart[3]),new DateTimeZone($estart[1]));
                } elseif (preg_match("/DTSTART;.*TZID=(.*):(.*)\b/",$event['data'],$estart)) {
                	$tmpstart = DateTime::createFromFormat('YmdHis',str_replace("T","",$estart[2]));
                } elseif (preg_match("/DTSTART(:)(.*)Z\b/",$event['data'],$estart)) {
                        $tmpstart = DateTime::createFromFormat('YmdHis',str_replace("T","",$estart[2]),new DateTimeZone("UTC"));
                } elseif (preg_match("/DTSTART;(VALUE=DATE):(.*)\b/",$event['data'],$estart)) {
			$teststart = $vdstart;
			$estart[2] .= "T000000";
                        $tmpstart = DateTime::createFromFormat('YmdHis',str_replace("T","",$estart[2]));
                }
		date_timezone_set($tmpstart,$localTZ);
		$tmpWKDay = date_format($tmpstart,"N");
		$tmpfwDay = date_interval_format(date_diff(new DateTime(date("Y-m-d",$ustart+($delay*60))),$tmpstart,false),"%r%a");
		$tmpstart = date_format($tmpstart,"U");
		$tmpstart -= $datediff;
                if (preg_match("/DTEND;.*TZID=(.*);(VALUE=DATE):(.*)\b/",$event['data'],$eend)) {
									$eend[3] .= "T000000";
                  $tmpend = DateTime::createFromFormat('YmdHis',str_replace("T","",$eend[3]),new DateTimeZone($eend[1]));
                } elseif (preg_match("/DTEND;.*TZID=(.*):(.*)\b/",$event['data'],$eend)) {
                	$tmpend = DateTime::createFromFormat('YmdHis',str_replace("T","",$eend[2]),new DateTimeZone($eend[1]));
                } elseif (preg_match("/DTEND(:)(.*)Z\b/",$event['data'],$eend)) {
                	$tmpend = DateTime::createFromFormat('YmdHis',str_replace("T","",$eend[2]),new DateTimeZone("UTC"));
                } elseif (preg_match("/DTEND;(VALUE=DATE):(.*)\b/",$event['data'],$eend)) {
                	$eend[2] .= "T000000";
                	$tmpend = DateTime::createFromFormat('YmdHis',str_replace("T","",$eend[2]));
                }
                $tmpend = date_format($tmpend,"U");
                $tmpend -= $datediff;
		$diff = $tmpend - $tmpstart;
		if ( $results[$ematch[2]]["Start"] == -1 || $tmpstart < $results[$ematch[2]]["Start"]) {
			if ( preg_match("/RRULE:(.*)/",$event['data'],$rrule)>0 ) {
                                //RRULE vorhanden Tag nächsten Termin suchen
//print "nächsten Termin finden/n";
                                $results[$ematch[2]]["RRule"] = $rrule[1];
				preg_match_all("/EXDATE.*:(.*)\s/iU",$event['data'],$resExDates, PREG_PATTERN_ORDER);
				foreach ($resExDates[1] AS $d => $ExDate) {
					$ExDates[] = $ExDate;
				}
				if (preg_match("/RDATE.*:(.*)\s/",$event['data'],$resRDates)) {
					$RDates = explode(",",$resRDates[1]);
				}
				$recurr = new \Recurr\Rule;
				$recurr->loadFromString(trim($rrule[1]));
				$recurr->setStartDate(new DateTime($estart[2]));
				if (isset($ExDates)) $recurr->setExDates($ExDates);
				if (isset($RDates)) $recurr->setRDates($RDates);
				$constraint = new \Recurr\Transformer\Constraint\BetweenConstraint(DateTime::createFromFormat("U",$teststart), DateTime::createFromFormat("U",$uend),True);
				$transformer = new \Recurr\Transformer\ArrayTransformer();
				$recresult = $transformer->transform($recurr,$constraint);
				$recresult = $recresult->startsAfter(DateTime::createFromFormat("U",$teststart),true);
				$iterator = $recresult->getIterator();
				$iterator->uasort(function ($a, $b) {
		    			return ($a->getStart() < $b->getStart()) ? -1 : 1;
				});
				$recresult = new \Recurr\RecurrenceCollection(iterator_to_array($iterator));
				if (isset($recresult)) $date = $recresult->first()->getStart();
			        if (isset($date) && $uend>=$date->getTimestamp() && ( $results[$ematch[2]]["Start"] == -1 || ($date->getTimestamp() - $datediff) <= $results[$ematch[2]]["Start"])) {
                                        $results[$ematch[2]]["Start"] = $date->getTimestamp() - $datediff;
                                        $results[$ematch[2]]["End"] = $results[$ematch[2]]["Start"] + $diff;
                                        $results[$ematch[2]]["wkDay"] = date("N",$date->getTimestamp());
                                        $results[$ematch[2]]["fwDay"] = date_interval_format(date_diff(new DateTime(date("Y-m-d",$ustart+($delay*60))),$date,false),"%r%a");
	                                $results[$ematch[2]]["Summary"] = $ematch[1];
	                                if ( preg_match("/DESCRIPTION:([^\r\n]*)/",$event['data'],$desc) ) $results[$ematch[2]]["Desc"] = $desc[1];
                                }
                        } else {
				$results[$ematch[2]]["Start"] = $tmpstart;
				$results[$ematch[2]]["wkDay"] = $tmpWKDay;
				$results[$ematch[2]]["fwDay"] = $tmpfwDay;
				$results[$ematch[2]]["End"] = $tmpend;
				$results[$ematch[2]]["Summary"] = $ematch[1];
				if ( preg_match("/DESCRIPTION:([^\r\n]*)/",$event['data'],$desc) ) $results[$ematch[2]]["Desc"] = $desc[1];
			}
		}
		
	}
}
echo "{\n";

$timeend = microtime(true) - $timestart;
//echo "$timeend - Daten ausgeben.\n";
foreach ( $sevents AS $k => $event ) {
	$tmp = $results[$event];
	echo "\t\"$event\": {\n";
	if (isset($debug)) {
                echo "\t\t\"hStart\": \"".date("d.m.Y H:i:s",$tmp["Start"]+$datediff)."\",\n";
                echo "\t\t\"hEnd\": \"".date("d.m.Y H:i:s",$tmp["End"]+$datediff)."\",\n";
        }
	//patch for localtime timestamp for the MiniServer
	$tmp["Start"] += date("I",$tmp["Start"]+$datediff)*3600;
	$tmp["End"] += date("I",$tmp["End"]+$datediff)*3600;
	echo "\t\t\"Start\": ".$tmp["Start"].",\n";
	echo "\t\t\"End\": ".$tmp["End"].",\n";
	echo "\t\t\"Summary\": \"".str_replace('\,',',',$tmp["Summary"])."\",\n";
	echo "\t\t\"Description\": \"".str_replace('\,',',',$tmp["Desc"])."\",\n";
	echo "\t\t\"fwDay\": ".$tmp["fwDay"].",\n";
	echo "\t\t\"wkDay\": ".$tmp["wkDay"]."\n\t},\n";
}
if (isset($debug)) echo "\t\"hnow\": \"".date("d.m.Y H:i:s")."\",\n";
echo "\t\"now\": ".(time()-$datediff+date("I")*3600)."\n";
echo "}\n";
$timeend = microtime(true) - $timestart;
//echo "$timeend - Script beendet, $countevents Kalendereinträge.\n";
?>

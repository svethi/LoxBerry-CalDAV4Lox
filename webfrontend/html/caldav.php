<?php

/*
* @author    Sven Thierfelder & Christian Fenzl
* @copyright SD-Thierfelder & Christian Fenzl
*/

header('Content-Type: text/html; charset=utf-8');

require_once("class_caldav.php");
require_once("RRule.php");

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

if (preg_match("/google\.com\/calendar/",$calURL)) {
	//Google Kalender
	$events = array();
	if( $cache > 0 ) {
		if(filemtime($myFile) < date("U")-($cache * 60)) {
			$fh = fopen($myFile, 'w') or die("can't open file");
			$context = stream_context_create(array(
				'http' => array(
					'header'  => "Authorization: Basic " . base64_encode("$user:$pass"))
			));
			$Datei = file_get_contents($calURL, false, $context);
			fwrite($fh, $Datei);
			fclose($fh);
		} else {
			$Datei = file_get_contents($myFile);
		}
	} else {
		$context = stream_context_create(array(
			'http' => array(
				'header'  => "Authorization: Basic " . base64_encode("$user:$pass"))
		));
		$Datei = file_get_contents($calURL, false, $context);
	}
	preg_match_all("/(BEGIN:VEVENT.*END:VEVENT)/isU",$Datei,$gevents, PREG_PATTERN_ORDER);
	foreach ( $gevents[1] AS $e => $event ) {
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
		if ( date_format($eend, "U") >= $ustart  && date_format($estart, "U") <= $uend ) {
			$events[]['data']=$event;
		} elseif (preg_match("/RRULE:(.*)/",$event,$rrule)) {
			//Wiederholungen testen
			$nEvent = new RRule( new iCalDate(date_format($estart,"Ymd\THis")), $rrule[1] );
			$date = time();
			do {
				$date = $nEvent->GetNext();
			}
			while( isset($date) && ($ustart>$date->_epoch+$diff || preg_match("/EXDATE;.*".date("Ymd\THis",$date->_epoch)."/",$event)) );
			if ( isset($date) && $uend>=$date->_epoch) {
				$event = preg_replace("/RRULE:.*[\n]/","",$event);
				$event = preg_replace("/DTSTART.*/","DTSTART;TZID=".date("e").":".date("Ymd\THis",$date->_epoch),$event);
				$event = preg_replace("/DTEND.*/","DTEND;TZID=".date("e").":".date("Ymd\THis",$date->_epoch + $diff),$event);
				$events[]['data']=$event;
			}
		}
	}
} else {	
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
                                $nEvent = new RRule( new iCalDate($estart[2]), $rrule[1] );
                                $date = time();
                                do {
                                        $date = $nEvent->GetNext();
//print date("d.m.Y",$date->_epoch)."\n";
                                }
                                while( isset($date) && ($ustart>$date->_epoch+$diff || preg_match("/EXDATE;.*".date("Ymd\THis",$date->_epoch)."/",$event['data'])) );
                                if ( isset($date) && $uend>=$date->_epoch && ( $results[$ematch[2]]["Start"] == -1 || ($date->_epoch - $datediff) <= $results[$ematch[2]]["Start"])) {
                                        $results[$ematch[2]]["Start"] = $date->_epoch - $datediff;
                                        $results[$ematch[2]]["End"] = $results[$ematch[2]]["Start"] + $diff;
                                        $results[$ematch[2]]["wkDay"] = date("N",$date->_epoch);
                                        $results[$ematch[2]]["fwDay"] = date_interval_format(date_diff(new DateTime(date("Y-m-d",$ustart+($delay*60))),new DateTime("@$date->_epoch"),false),"%r%a");
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
?>

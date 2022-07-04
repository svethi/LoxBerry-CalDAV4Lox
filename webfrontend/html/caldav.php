<?php

/*
* @author    Sven Thierfelder & Christian Fenzl
* @copyright SD-Thierfelder & Christian Fenzl
*/

$timestart = microtime(true);
header('Content-Type: text/html; charset=utf-8');

require_once("class_caldav.php");
require __DIR__ . '/vendor/autoload.php';
require_once "loxberry_system.php";

//use Recurr\Rule;
use Sabre\VObject;

date_default_timezone_set(date("e"));

$calURL = @($_GET["calURL"]);
$user = @($_GET["user"]);
$pass = @($_GET["pass"]);
$fwdays = @($_GET["fwdays"]);
$getNextEvents=False;
$sevents = @explode("|",$_GET["events"]);
if (array_search("*",$sevents) !== False) {
	$getNextEvents = True;
	unset($sevents[array_search("*",$sevents)]);
}
$search = @implode("|",$sevents);
//$search = @($_GET["events"]);
//$search = preg_replace("#\*(\|?)#","$1",$search);
//$search = preg_replace("#^\|#","",$search);
$delay = @($_GET["delay"]);
$debug = @($_GET["debug"]);
$cache = @($_GET["cache"]);
$mqttpretopic = "caldav4lox/";

$mqttplugin = LBSystem::plugindata("mqttgateway");
if ($mqttplugin) {
	if (version_compare($mqttplugin['PLUGINDB_VERSION'], '0.9', '<')) {
		//eventuell bei späterem Logging zu kleine mqtt version loggen
		$mqtt = false;
	} else {
		$mqttplugin = $mqttplugin['PLUGINDB_FOLDER'];
		$mqttcfg = file_get_contents("$lbhomedir/config/plugins/$mqttplugin/mqtt.json");
		$mqttcfg = json_decode($mqttcfg,true);
		$mqttcfg = $mqttcfg["Main"];
		$test = exec("netstat -ul | grep ".$mqttcfg["udpinport"]);
		if (strlen($test) > 0) {
			        $mqtt = true;
		} else {
			        $mqtt = false;
		}		
	}

}

$myFile = "$lbpdatadir/caldav_".MD5($calURL).".ical";

//Get depth from conffile
$caldavconf = parse_ini_file("$lbpconfigdir/caldav4lox.conf");

$depth = $caldavconf['Depth'];

if (!isset($delay)) $delay = 60;

$localTZ = new DateTimeZone(date("e"));

$dummycal = new \Sabre\VObject\Component\VCalendar();
$dummyevent = $dummycal->createComponent('VEVENT');
$dummyevent->SUMMARY = "";
$dummyevent->DTSTART = "20081231T235959";
$dummyevent->DTEND = "20081231T235959";


foreach ( $sevents AS $e => $event ) {
	$results[$event] = clone $dummyevent;
}

$next = array();

$ustart = time()-($delay * 60);
$dtstart = new DateTime("@$ustart", $localTZ); //new DateTimeZone("UTC"));
$vdstart = mktime(0,0,0,date("m",$ustart),date("d",$ustart),date("Y",$ustart));
$start = gmdate("Ymd\THis\Z",$ustart);
$uend = mktime(date("H"),date("i")+$delay,date("s"),date("m"),date("d")+$fwdays,date("Y"));
$dtend = new DateTime("@$uend", $localTZ); //new DateTimeZone("UTC"));
$end = gmdate("Ymd\THis\Z",$uend);

$datediff = mktime(0,0,0,1,1,2009);
//echo $datediff,"\n";
$dst_offset = getDSTOffset(2009);
//echo $dst_offset,"\n";
if (date("I",$datediff) == 1) $datediff += $dst_offset;
//echo $datediff,"\n";

$timeend = microtime(true) - $timestart;
//echo "$timeend - Start Kalenderabholung\n";

//if (preg_match("/google\.com\/calendar/",$calURL)) {

function curl_get_contents($url,$user,$pass) {
	   $ch=curl_init($url);
	   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	   curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	   curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
	   curl_setopt($ch, CURLOPT_FAILONERROR, true);
	   //curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	   //curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	   //curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	   curl_setopt($ch, CURLOPT_HTTPHEADER, array('User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:47.0) Gecko/20100101 Firefox/47.0'));
	   $result = curl_exec($ch);
	   if (curl_errno($ch)) {
		echo "curl-Error: ".curl_error($ch)."\n";
	   }
	   //$curl_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	   //if ($curl_code >= 400) {
		//echo "calendar returned: $curl_code";
	   //}
	   return $result;
}

if (preg_match("|\/.*\.ics[/?]{0,1}|",$calURL)) {
	//iCal Kalender
	//echo "iCal Kalender erkannt\n";
	$events = array();
	if( $cache > 0 ) {
		if(filemtime($myFile) < date("U")-($cache * 60)) {
			//echo "Cachefile abgelaufen";
			$timeend = microtime(true) - $timestart;
			//echo "$timeend - Lade Kalender von Google";
			$fh = fopen($myFile, 'w') or die("can't open file");

			set_error_handler(
			create_function(
			'$severity, $message, $file, $line',
			'throw new ErrorException($message, $severity, $severity, $file, $line);'
			)
			);
			try {
				$Datei = curl_get_contents($calURL,$user,$pass);
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
		set_error_handler(
		create_function(
		'$severity, $message, $file, $line',
		'throw new ErrorException($message, $severity, $severity, $file, $line);'
		)
		);
		try {
			$Datei = curl_get_contents($calURL,$user,$pass);
		}
		catch (Exception $e) {
			echo $e->getMessage();
		}

		restore_error_handler();
	}
	$timeend = microtime(true) - $timestart;
	//echo "$timeend - Beginne mit Eintragssplitting\n";
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
		$Datei = "BEGIN:VCALENDAR";
		foreach ( $events AS $k => $event ) {
			preg_match("/(BEGIN:VCALENDAR(.*?)(BEGIN.*)END:VCALENDAR)/s",$event['data'],$tmp);
			if (strlen($Datei) == 15) {$Datei .= $tmp[2];}
			$Datei .= $tmp[3];
		}
		if (!count($events)) $Datei .= "\n";
		$Datei .= "END:VCALENDAR\n";
		$cal = "";
		$events = "";
	}
	catch (Exception $e) {
		echo $e->getMessage();
	}
	restore_error_handler();
}

//echo $Datei;

//print "$start:$end\n";
//print_r($events);

try {
	$calendar = VObject\Reader::read($Datei);
	$calendar = $calendar->expand($dtstart, $dtend, $localTZ);
}
catch (Exception $e) {
	echo "error loading events";
	print_r($e);
}

//sort events array

foreach ($calendar->VEVENT as $event) {
		if (isset($result)) {
			for ($x = 0; $x < sizeof($result); $x++) {
				if ($event->DTSTART->getDateTime($localTZ) < $result[$x]->DTSTART->getDateTime($localTZ)){
					array_splice($result,$x,0,[clone $event]);
					break;
				}
			}
			if ($x == sizeof($result)) { $result[] = clone $event; }
		} else {
			$result = [clone $event];
		}
}

//filter first event for search events from next events

foreach ($sevents as $sevent) {
	foreach ($result as $event) {
		if (preg_match("/(.*($sevent)[^\r\n]*)/",$event->SUMMARY,$ematch)) {
			$results[$sevent]=clone $event;
			break;
		}
	}
}

//print_r($result);

//initialize json results
unset($resjson);

$timeend = microtime(true) - $timestart;
//echo "$timeend - Daten ausgeben.\n";
foreach ( $sevents AS $k => $event ) {
	$tmp = $results[$event];
	$tmpstart = $tmp->DTSTART->getDateTime($localTZ);
	//date_timezone_set($tmpstart,$localTZ);
	if ($tmp->DTSTART == "20081231T235959") {
		$tmpWKDay = "-1";
		$tmpfwDay = "-1";
	} else {
		$tmpWKDay = $tmpstart->format("N");
		$tmpfwDay = date_interval_format(date_diff(new DateTime(date("Y-m-d",$ustart+($delay*60))),$tmpstart,false),"%r%a");
	}
	$tmpstart = $tmpstart->format("U") - $datediff;
	if (isset($tmp->DTEND)) {
		$tmpend = $tmp->DTEND->getDateTime($localTZ);
	} else {
		$tmpend = $tmp->DTSTART->getDateTime($localTZ);
	}
	$tmpend = $tmpend->format("U") - $datediff;
	unset($resevent);
	if (isset($debug)) {
		$resevent["hStart"] = date("d.m.Y H:i:s",$tmpstart+$datediff);
		$resevent["hEnd"] = date("d.m.Y H:i:s",$tmpend+$datediff);
	}
	//handle dst
	$dst_offset = getDSTOffset(date("Y",$tmpstart+$datediff));
	$tmpstart += date("I",$tmpstart+$datediff)*$dst_offset;
	$tmpend += date("I",$tmpend+$datediff)*$dst_offset;
	$resevent["Start"] = $tmpstart;
	$mqttevent = $event;
	if (strlen($mqttevent) == 0) { $mqttevent = "next";}
	sendMQTT("events/$mqttevent/Start",$tmpstart);
	$resevent["End"] = $tmpend;
	sendMQTT("events/$mqttevent/End",$tmpend);
	$resevent["Summary"] = str_replace('\,',',',$tmp->SUMMARY);
	sendMQTT("events/$mqttevent/Summary",str_replace('\,',',',$tmp->SUMMARY));
	$resevent["Description"] = str_replace('\,',',',$tmp->DESCRIPTION);
	sendMQTT("events/$mqttevent/Description",str_replace('\,',',',$tmp->DESCRIPTION));
	$resevent["fwDay"] = $tmpfwDay;
	sendMQTT("events/$mqttevent/fwDay",$tmpfwDay);
	$resevent["wkDay"] = $tmpWKDay;
	sendMQTT("events/$mqttevent/wkDay",$tmpWKDay);
	$resevent["now"] = (time()-$datediff+date("I")*$dst_offset);
	sendMQTT("events/$mqttevent/now",(time()-$datediff+date("I")*$dst_offset));
	$resjson[$event] = $resevent;
}

//Liste der nächsten Events
unset($resnext);
unset($resevent);
if ($getNextEvents) {
	unset($tmp);
	$cnt=0;
	foreach ( $result AS $event ) {
		$cnt+=1;
		if (isset($tmp)) {$nextEvents .= ",\n";}
		$tmp = $event;
		$tmpstart = $tmp->DTSTART->getDateTime($localTZ);
		//date_timezone_set($tmpstart,$localTZ);
		$tmpWKDay = $tmpstart->format("N");
		$tmpfwDay = date_interval_format(date_diff(new DateTime(date("Y-m-d",$ustart+($delay*60))),$tmpstart,false),"%r%a");
		$tmpstart = $tmpstart->format("U") - $datediff;
		if (isset($tmp->DTEND)) {
			$tmpend = $tmp->DTEND->getDateTime($localTZ);
		} else {
			$tmpend = $tmp->DTSTART->getDateTime($localTZ);
		}
		$tmpend = $tmpend->format("U") - $datediff;
		$resevent["number"] = $cnt;
		if (isset($debug)) {
			$resevent["hStart"] = date("d.m.Y H:i:s",$tmpstart+$datediff);
			$resevent["hEnd"] = date("d.m.Y H:i:s",$tmpend+$datediff);
		}
		//handle dst
		$dst_offset = getDSTOffset(date("Y",$tmpstart+$datediff));
		$tmpstart += date("I",$tmpstart+$datediff)*$dst_offset;
		$tmpend += date("I",$tmpend+$datediff)*$dst_offset;
		$resevent["Start"] = $tmpstart;
		$mqttevent = $event->SUMMARY;
		if (strlen($mqttevent) == 0) { $mqttevent = "next";}
		$resevent["End"] = $tmpend;
		$resevent["Summary"] = str_replace('\,',',',$tmp->SUMMARY);
		$resevent["Description"] = str_replace('\,',',',$tmp->DESCRIPTION);
		$resevent["fwDay"] = $tmpfwDay;
		$resevent["wkDay"] = $tmpWKDay;
		$resevent["now"] = (time()-$datediff+date("I")*$dst_offset);
		$resnext[] = $resevent;
	}
	$resjson["next"] = $resnext;
	//$resnext = json_encode($resnext,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
	unset($nextEvents);
	$nextEvents["data"] = $resnext;
	sendMQTT("events/next", json_encode($nextEvents,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
}
if (isset($debug)) $resjson["hnow"] = date("d.m.Y H:i:s");
$dst_offset = getDSTOffset(date("Y"));
$resjson["now"] = (time()-$datediff+date("I")*$dst_offset);
sendMQTT("events/now",(time()-$datediff+date("I")*$dst_offset));

echo json_encode($resjson,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
	
$timeend = microtime(true) - $timestart;
//echo $timeend - Script beendet, $countevents Kalendereinträge.\n";

function sendMQTT($topic,$value,$retain = false) {
	global $mqttcfg, $mqtt, $mqttpretopic;
	if ($mqtt) {
		if ($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
			$message["topic"] = "$mqttpretopic$topic";
			$message["value"] = $value;
			$message["retain"] = $retain;
			$message = json_encode($message,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
			socket_sendto($socket, $message, strlen($message), 0, "127.0.0.1", $mqttcfg["udpinport"]);
		}
	}
}

function getDSTOffset($year = NULL) {

	if (is_null($year)) $year = date("Y");
	$timezone = new DateTimeZone(date("e"));
	$transitions = $timezone->getTransitions(mktime(0,0,0,1,1,$year),mktime(0,0,0,12,30,$year));

	foreach ($transitions as $transition) {
		if ($transition["isdst"] == 1 && !isset($dst_offset)) {
			$dst_offset = $transition["offset"];
		}
		if ($transition["isdst"] == 0 && !isset($st_offset)) {
			$st_offset = $transition["offset"];
		}
	}
	if (!isset($st_offset)) $st_offset = date("Z");

	if (isset($dst_offset)) {
		$dst_offset -= $st_offset;
	} else {
		$dst_offset = 0;
	}

	return $dst_offset;

}
?>

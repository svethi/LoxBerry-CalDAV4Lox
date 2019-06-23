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
$sevents = @explode("|",$_GET["events"]);
$search = @($_GET["events"]);
$delay = @($_GET["delay"]);
$debug = @($_GET["debug"]);
$cache = @($_GET["cache"]);
$mqttpretopic = "caldav4lox/";

$mqttplugin = LBSystem::plugindata("mqttgateway");
if ($mqttplugin) {
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

$home = posix_getpwuid(posix_getuid());
$home = $home['dir'];

# Figure out in which subfolder we are installed

$psubfolder = __FILE__;
$psubfolder = preg_replace('/(.*)\/(.*)\/(.*)$/',"$2", $psubfolder);

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
		$Datei .= "END:VCALENDAR\n";
	}
	catch (Exception $e) {
		echo $e->getMessage();
	}
	restore_error_handler();
}

//echo $Datei;

$Datei = file_get_contents("/mnt/storage/dev/caldav4lox/webfrontend/html/test2.ics");

//print "$start:$end\n";
//print_r($events);
$calendar = VObject\Reader::read($Datei);
$calendar = $calendar->expand($dtstart, $dtend, $localTZ);

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
		if (preg_match("/(.*($search)[^\r\n]*)/",$event->SUMMARY,$ematch)) {
			$results[$sevent]=clone $event;
			break;
		}
	}
}

//print_r($result);
echo "{\n";

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
	$tmpend = $tmp->DTEND->getDateTime($localTZ);
	$tmpend = $tmpend->format("U") - $datediff;
	echo "\t\"$event\": {\n";
	if (isset($debug)) {
		echo "\t\t\"hStart\": \"".date("d.m.Y H:i:s",$tmpstart+$datediff)."\",\n";
		echo "\t\t\"hEnd\": \"".date("d.m.Y H:i:s",$tmpend+$datediff)."\",\n";
	}
	//handle dst
	$dst_offset = getDSTOffset(date("Y",$tmpstart+$datediff));
	$tmpstart += date("I",$tmpstart+$datediff)*$dst_offset;
	$tmpend += date("I",$tmpend+$datediff)*$dst_offset;
	echo "\t\t\"Start\": ".$tmpstart.",\n";
	$mqttevent = $event;
	if (strlen($mqttevent) == 0) { $mqttevent = "next";}
	sendMQTT("events/$mqttevent/start",$tmpstart);
	echo "\t\t\"End\": ".$tmpend.",\n";
	sendMQTT("events/$mqttevent/end",$tmpend);
	echo "\t\t\"Summary\": \"".str_replace('\,',',',$tmp->SUMMARY)."\",\n";
	sendMQTT("events/$mqttevent/summary",str_replace('\,',',',$tmp->SUMMARY));
	echo "\t\t\"Description\": \"".str_replace('\,',',',$tmp->DESCRIPTION)."\",\n";
	sendMQTT("events/$mqttevent/description",str_replace('\,',',',$tmp->DESCRIPTION));
	echo "\t\t\"fwDay\": ".$tmpfwDay.",\n";
	sendMQTT("events/$mqttevent/fwdays",$tmpfwDay);
	echo "\t\t\"wkDay\": ".$tmpWKDay."\n\t},\n";
	sendMQTT("events/$mqttevent/wkday",$tmpWKDay);
}
echo "\t\"next\": [\n";
//Liste der nächsten Events
unset($tmp);
foreach ( $result AS $event ) {
	if (isset($tmp)) {echo ",\n";}
	$tmp = $event;
	$tmpstart = $tmp->DTSTART->getDateTime($localTZ);
	//date_timezone_set($tmpstart,$localTZ);
	$tmpWKDay = $tmpstart->format("N");
	$tmpfwDay = date_interval_format(date_diff(new DateTime(date("Y-m-d",$ustart+($delay*60))),$tmpstart,false),"%r%a");
	$tmpstart = $tmpstart->format("U") - $datediff;
	$tmpend = $tmp->DTEND->getDateTime($localTZ);
	$tmpend = $tmpend->format("U") - $datediff;
	echo "\t\t{\n";
	if (isset($debug)) {
		echo "\t\t\t\"hStart\": \"".date("d.m.Y H:i:s",$tmpstart+$datediff)."\",\n";
		echo "\t\t\t\"hEnd\": \"".date("d.m.Y H:i:s",$tmpend+$datediff)."\",\n";
	}
	//handle dst
	$dst_offset = getDSTOffset(date("Y",$tmpstart+$datediff));
	$tmpstart += date("I",$tmpstart+$datediff)*$dst_offset;
	$tmpend += date("I",$tmpend+$datediff)*$dst_offset;
	echo "\t\t\t\"Start\": ".$tmpstart.",\n";
	$mqttevent = $event->SUMMARY;
	if (strlen($mqttevent) == 0) { $mqttevent = "next";}
	//sendMQTT("events/$mqttevent/start",$tmpstart);
	echo "\t\t\t\"End\": ".$tmpend.",\n";
	//sendMQTT("events/$mqttevent/end",$tmpend);
	echo "\t\t\t\"Summary\": \"".str_replace('\,',',',$tmp->SUMMARY)."\",\n";
	//sendMQTT("events/$mqttevent/summary",str_replace('\,',',',$tmp->SUMMARY));
	echo "\t\t\t\"Description\": \"".str_replace('\,',',',$tmp->DESCRIPTION)."\",\n";
	//sendMQTT("events/$mqttevent/description",str_replace('\,',',',$tmp->DESCRIPTION));
	echo "\t\t\t\"fwDay\": ".$tmpfwDay.",\n";
	//sendMQTT("events/$mqttevent/fwdays",$tmpfwDay);
	echo "\t\t\t\"wkDay\": ".$tmpWKDay."\n\t\t}";
	//sendMQTT("events/$mqttevent/wkday",$tmpWKDay);
}
echo "\n\t]\n";
if (isset($debug)) echo "\t\"hnow\": \"".date("d.m.Y H:i:s")."\",\n";
$dst_offset = getDSTOffset(date("Y"));
echo "\t\"now\": ".(time()-$datediff+date("I")*$dst_offset)."\n";
sendMQTT("events/$mqttevent/now",(time()-$datediff+date("I")*$dst_offset));
echo "}\n";
$timeend = microtime(true) - $timestart;
//echo $timeend - Script beendet, $countevents Kalendereinträge.\n";

function sendMQTT($topic,$value) {
	global $mqttcfg, $mqtt, $mqttpretopic;
	if ($mqtt) {
		if ($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
			$message = "$mqttpretopic$topic $value";
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

#!/usr/bin/perl

# This is a sample Script file
# It does not much:
#   * Loading configuration
#   * including header.htmlfooter.html
#   * and showing a message to the user.
# That's all.

use LoxBerry::Web;
use LoxBerry::Log;
use CGI qw/:standard/;
use warnings;
use strict;
no strict "refs"; # we need it for template system

my  $cfg;
my  $conf;
our $selecteddepth0 = "";
our $selecteddepth1 = "";
my  $curl;
our $depth;
our $caldavurl;
our $caldavuser;
our $caldavpass;
our $fwdays;
our $delay;
our $events;
our $dotest;
our $helptext;
our $helplink;
our $helptemplate;
our $template_title;
our $namef;
our $value;
our %query;
our $do;
our $cache;

my $log = LoxBerry::Log->new (
        name => 'cronjob',
        filename => "$lbplogdir/mylogfile.log",
        append => 1,
        addtime => 1
);
LOGSTART "start CalDAV-4-Lox configuration helper";
LOGDEB "LoxBerry Version: ".LoxBerry::System::lbversion();
LOGDEB "Plugin Version: ".LoxBerry::System::pluginversion();

LOGDEB "Read system settings";
# Read Settings
$cfg             = new Config::Simple("$lbsconfigdir/general.cfg");
$curl            = $cfg->param("BINARIES.CURL");
LOGDEB "Done";

LOGDEB "retrieve values from URL";
# Everything from URL
foreach (split(/&/,$ENV{'QUERY_STRING'}))
{
  ($namef,$value) = split(/=/,$_,2);
  $namef =~ tr/+/ /;
  $namef =~ s/%([a-fA-F0-9][a-fA-F0-9])/pack("C", hex($1))/eg;
  $value =~ tr/+/ /;
  $value =~ s/%([a-fA-F0-9][a-fA-F0-9])/pack("C", hex($1))/eg;
  $query{$namef} = $value;
}

# Set parameters coming in - get over post
	if ( !$query{'do'} )           { if ( param('do')           ) { $do           = quotemeta(param('do'));           } else { $do           = "form"; } } else { $do           = quotemeta($query{'do'});           }
	if ( !$query{'caldavurl'} )    { if ( param('caldavurl')    ) { $caldavurl    = param('caldavurl');               } else { $caldavurl    = "";     } } else { $caldavurl    = $query{'caldavurl'};               }
	if ( !$query{'caldavuser'} )   { if ( param('caldavuser')   ) { $caldavuser   = param('caldavuser');              } else { $caldavuser   = "";     } } else { $caldavuser   = $query{'caldavuser'};              }
	if ( !$query{'caldavpass'} )   { if ( param('caldavpass')   ) { $caldavpass   = param('caldavpass');              } else { $caldavpass   = "";     } } else { $caldavpass   = $query{'caldavpass'};              }
	if ( !$query{'fwdays'} )       { if ( param('fwdays')       ) { $fwdays       = param('fwdays');                  } else { $fwdays       = "";     } } else { $fwdays       = $query{'fwdays'};                  }
	if ( !$query{'delay'} )        { if ( param('delay')        ) { $delay        = param('delay');                   } else { $delay        = "";     } } else { $delay        = $query{'delay'};                   }
	if ( !$query{'events'} )       { if ( param('events')       ) { $events       = param('events');                  } else { $events       = "";     } } else { $events       = $query{'events'};                  }
	if ( !$query{'dotest'} )       { if ( param('dotest')       ) { $dotest       = param('dotest');                  } else { $dotest       = "";     } } else { $dotest       = $query{'dotest'};                  }
  if ( !$query{'cache'} )        { if ( param('cache')        ) { $cache        = param('cache');                   } else { $cache        = "";     } } else { $cache        = $query{'cache'};                   }
LOGDEB "Done";

LOGDEB "read CalDAV-4-Lox settings";
# read caldav4lox configs
$conf = new Config::Simple("$lbpconfigdir/caldav4lox.conf");
$depth = $conf->param('general.Depth');
LOGDEB "Done";
if ( $depth == 0 ) {$selecteddepth0="selected"} else { $selecteddepth1="selected"}

LOGDEB "retrieve the local ip";
my $localip = LoxBerry::System::get_localip();
LOGDEB "Done";

LOGDEB "retrieve the defaul gateway";
my $gw = `netstat -nr`;
$gw =~ m/0.0.0.0\s+([0-9]+.[0-9]+.[0-9]+.[0-9]+)/g;
my $gwip = $1;
LOGDEB "Done";

LOGDEB "create the page - beginn";
# Title
$template_title = "CalDAV-4-Lox";
# Create help page
$helplink = "http://www.loxwiki.eu/display/LOXBERRY/CalDAV-4-Lox";
$helptemplate = "help.html";
LOGDEB "print out the header";
LoxBerry::Web::lbheader(undef,$helplink,$helptemplate);

LOGDEB "create the content";
# Load content from template
my $maintemplate = HTML::Template->new(
    filename => "$lbptemplatedir/content.html",
    global_vars => 1,
    loop_context_vars => 1,
    die_on_bad_params => 0,
);
$maintemplate->param("psubfolder",$lbpplugindir);
$maintemplate->param("selecteddepth0", $selecteddepth0);
$maintemplate->param("selecteddepth1", $selecteddepth1);
$maintemplate->param("lang",lblanguage());
$maintemplate->param("caldavurl",$caldavurl);
$maintemplate->param("caldavuser",$caldavuser);
$maintemplate->param("caldavpas",$caldavpass);
$maintemplate->param("fwdays",$fwdays);
$maintemplate->param("delay",$delay);
$maintemplate->param("cache",$cache);
$maintemplate->param("events",$events);

%L = LoxBerry::System::readlanguage($maintemplate, "language.ini");
  
print $maintemplate->output;

if ( $caldavurl =~ m{
    (
        (ftp|https?):\/\/
        ([a-z0-9\-_]+(:[^@]+)?\@)?
        (
            ([a-z0-9\.\-]+)\.([a-z\.]{2,6})
            |
            ([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})
        )
        (:[0-9]{2,5})?
        (
            [a-z0-9\.\-_/\+\%&;\:,\=\!@\(\)\[\]~\'\"]*
            [a-z0-9\.\-_/\+\%&;\:,\=\!@\(\)\[\]~]+
        )
        (\?[a-z0-9\.\-_/\+\%&;\:,\=\!@\(\)\[\]~]*)?
        (\#[a-z0-9\.\-_/\+\%&;\:,\=\!@\(\)\[\]~]*)?
    )
}gisx) {
	LOGINF "URL was given, generate answer";
	my $tempcalurl = $caldavurl; 
	$tempcalurl =~ s/\:/\%3A/g;
	my $tempevents = $events;
	$tempevents =~ s/\n/\|/g;
	$tempevents =~ s/\r//g;
	$tempevents =~ s/ //g;
	my $tempURL = "http://$localip/plugins/$lbpplugindir/caldav.php?calURL=$tempcalurl&user=$caldavuser&pass=$caldavpass";
	if ( $fwdays ) { if (($fwdays > 0) && ($fwdays < 364)) {$tempURL .= "&fwdays=$fwdays";}}
  if ( $delay ) { if (($delay > 0) && ($fwdays < 1440)) {$tempURL.= "&delay=$delay";}}
  if ( $cache ) { if (($cache > 0) && ($cache < 1440)) {$tempURL.= "&cache=$cache";}}
	$tempURL .= "&events=$tempevents";
	print "<p>". $L{"LABEL.TXT0006"} . ": <a href=$tempURL target='_blank'>$tempURL</a></p>\n";
	LOGDEB "test the calendar";
	my $test = `$curl '$tempURL'`;
	print "<p><pre class=\"textfield\">$test</pre></p>";
	LOGDEB "Done";
	if ($test eq "") {LOGWARN "no answer from curl"}
	print "<p>" . $L{"LABEL.TXT0000"} . ":\n";
	if ($tempevents eq "") {print "<p></p>\n";}
	foreach (split(/\|/,$tempevents))
	{
	print "<p>$_:</ br><ul style=\"display: table;\">\n<li style=\"display: table-row;\"><div style=\"width: 15%; display: table-cell;\">" . $L{"LABEL.TXT0001"} . "</div>: <span style=\"background-color: #cccccc\">$_\": {\\i\"Start\"\\i: \\v</span></li>\n<li style=\"display: table-row;\"><div style=\"width: 15%; display: table-cell;\">" . $L{"LABEL.TXT0002"} . "</div>: <span style=\"background-color: #cccccc\">$_\": {\\i\"End\"\\i: \\v</span></li>\n<li style=\"display: table-row;\"><div style=\"width: 15%; display: table-cell;\">" . $L{"LABEL.TXT0003"} . "</div>: <span style=\"background-color: #cccccc\">$_\": {\\i\"fwDay\"\\i: \\v</span></li>\n<li style=\"display: table-row;\"><div style=\"width: 15%; display: table-cell;\">" . $L{"LABEL.TXT0004"} . "</div>: <span style=\"background-color: #cccccc\">$_\": {\\i\"wkDay\"\\i: \\v</span></li>\n</ul></p>";
	}
	print $L{"LABEL.TXT0005"} . ": <span style=\"background-color: #cccccc\">\"now\": \\v</span></p>\n";
}

LOGDEB "print out the footer";
# Load footer and replace HTML Markup <!--$VARNAME--> with perl variable $VARNAME
LoxBerry::Web::lbfooter();
LOGEND "Done";

exit;

#!/usr/bin/perl

# This is a sample Script file
# It does not much:
#   * Loading configuration
#   * including header.htmlfooter.html
#   * and showing a message to the user.
# That's all.

use File::HomeDir;
use CGI qw/:standard/;
use Config::Simple;
use Cwd 'abs_path';
use IO::Socket::INET;
use warnings;
use strict;
no strict "refs"; # we need it for template system

my  $home = File::HomeDir->my_home;
our $lang;
my  $installfolder;
my  $cfg;
my  $conf;
our $psubfolder;
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
our $template_title;
our $namef;
our $value;
our %query;
our $do;
our $phrase;
our $phraseplugin;
our $languagefile;
our $languagefileplugin;
our $cache;

# Read Settings
$cfg             = new Config::Simple("$home/config/system/general.cfg");
$installfolder   = $cfg->param("BASE.INSTALLFOLDER");
$lang            = $cfg->param("BASE.LANG");
$curl            = $cfg->param("BINARIES.CURL");

print "Content-Type: text/html\n\n";

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
	if ( !$query{'lang'} )         { if ( param('lang')         ) { $lang         = quotemeta(param('lang'));         } else { $lang         = $lang;  } } else { $lang         = quotemeta($query{'lang'});         }
	if ( !$query{'do'} )           { if ( param('do')           ) { $do           = quotemeta(param('do'));           } else { $do           = "form"; } } else { $do           = quotemeta($query{'do'});           }
	if ( !$query{'caldavurl'} )    { if ( param('caldavurl')    ) { $caldavurl    = param('caldavurl');               } else { $caldavurl    = "";     } } else { $caldavurl    = $query{'caldavurl'};               }
	if ( !$query{'caldavuser'} )   { if ( param('caldavuser')   ) { $caldavuser   = param('caldavuser');              } else { $caldavuser   = "";     } } else { $caldavuser   = $query{'caldavuser'};              }
	if ( !$query{'caldavpass'} )   { if ( param('caldavpass')   ) { $caldavpass   = param('caldavpass');              } else { $caldavpass   = "";     } } else { $caldavpass   = $query{'caldavpass'};              }
	if ( !$query{'fwdays'} )       { if ( param('fwdays')       ) { $fwdays       = param('fwdays');                  } else { $fwdays       = "";     } } else { $fwdays       = $query{'fwdays'};                  }
	if ( !$query{'delay'} )        { if ( param('delay')        ) { $delay        = param('delay');                   } else { $delay        = "";     } } else { $delay        = $query{'delay'};                   }
	if ( !$query{'events'} )       { if ( param('events')       ) { $events       = param('events');                  } else { $events       = "";     } } else { $events       = $query{'events'};                  }
	if ( !$query{'dotest'} )       { if ( param('dotest')       ) { $dotest       = param('dotest');                  } else { $dotest       = "";     } } else { $dotest       = $query{'dotest'};                  }
  if ( !$query{'cache'} )        { if ( param('cache')        ) { $cache        = param('cache');                   } else { $cache        = "";     } } else { $cache        = $query{'cache'};                   }

# Figure out in which subfolder we are installed

$psubfolder = abs_path($0);
$psubfolder =~ s/(.*)\/(.*)\/(.*)$/$2/g;

# read caldav4lox configs
$conf = new Config::Simple("$home/config/plugins/$psubfolder/caldav4lox.conf");
$depth = $conf->param('general.Depth');
if ( $depth == 0 ) {$selecteddepth0="selected"} else { $selecteddepth1="selected"}

# Init Language
	# Clean up lang variable
	$lang         =~ tr/a-z//cd; $lang         = substr($lang,0,2);
  # If there's no language phrases file for choosed language, use german as default
		if (!-e "$installfolder/templates/system/$lang/language.dat") 
		{
  		$lang = "de";
	}
	# Read translations / phrases
		$languagefile 			= "$installfolder/templates/system/$lang/language.dat";
		$phrase 						= new Config::Simple($languagefile);
		$languagefileplugin = "$installfolder/templates/plugins/$psubfolder/$lang/language.dat";
		$phraseplugin 			= new Config::Simple($languagefileplugin);

# Get Local IP and GW IP
my $sock = IO::Socket::INET->new(
                       PeerAddr=> "example.com",
                       PeerPort=> 80,
                       Proto   => "tcp");
my $localip = $sock->sockhost;

my $gw = `netstat -nr`;
$gw =~ m/0.0.0.0\s+([0-9]+.[0-9]+.[0-9]+.[0-9]+)/g;
my $gwip = $1;

# Title
$template_title = $phrase->param("TXT0000") . ": CalDAV-4-Lox";

# Create help page
$helplink = "http://www.loxwiki.eu/display/LOXBERRY/CalDAV-4-Lox";
open(F,"$installfolder/templates/plugins/$psubfolder/$lang/help.html") || die "Missing template $lang/help.html";
  while (<F>) {
    $_ =~ s/<!--\$(.*?)-->/${$1}/g;
    $helptext .= $_;
  }
close(F);

# Load header and replace HTML Markup <!--$VARNAME--> with perl variable $VARNAME
open(F,"$installfolder/templates/system/$lang/header.html") || die "Missing template system/$lang/header.html";
  while (<F>) {
    $_ =~ s/<!--\$(.*?)-->/${$1}/g;
    print $_;
  }
close(F);

# Load content from template
open(F,"$installfolder/templates/plugins/$psubfolder/$lang/content.html") || die "Missing template $lang/content.html";
  while (<F>) {
    $_ =~ s/<!--\$(.*?)-->/${$1}/g;
    print $_;
  }
close(F);
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
	my $tempcalurl = $caldavurl; 
	$tempcalurl =~ s/\:/\%3A/g;
	my $tempevents = $events;
	$tempevents =~ s/\n/\|/g;
	$tempevents =~ s/\r//g;
	$tempevents =~ s/ //g;
	my $tempURL = "http://$localip/plugins/$psubfolder/caldav.php?calURL=$tempcalurl&user=$caldavuser&pass=$caldavpass";
	if ( $fwdays ) { if (($fwdays > 0) && ($fwdays < 364)) {$tempURL .= "&fwdays=$fwdays";}}
  if ( $delay ) { if (($delay > 0) && ($fwdays < 1440)) {$tempURL.= "&delay=$delay";}}
  if ( $cache ) { if (($cache > 0) && ($cache < 1440)) {$tempURL.= "&cache=$cache";}}
	$tempURL .= "&events=$tempevents";
	print "<p>". $phraseplugin->param("TXT0006") . ": <a href=$tempURL target='_blank'>$tempURL</a></p>\n";
	my $test = `$curl '$tempURL'`;
	print "<p><pre class=\"textfield\">$test</pre></p>";
	print "<p>" . $phraseplugin->param("TXT0000") . ":\n";
	if ($tempevents eq "") {print "<p></p>\n";}
	foreach (split(/\|/,$tempevents))
	{
		print "<p>$_:</ br><ul style=\"display: table;\">\n<li style=\"display: table-row;\"><div style=\"width: 15%; display: table-cell;\">" . $phraseplugin->param("TXT0001") . "</div>: <span style=\"background-color: #cccccc\">$_\": {\\i\"Start\"\\i: \\v</span></li>\n<li style=\"display: table-row;\"><div style=\"width: 15%; display: table-cell;\">" . $phraseplugin->param("TXT0002") . "</div>: <span style=\"background-color: #cccccc\">$_\": {\\i\"End\"\\i: \\v</span></li>\n<li style=\"display: table-row;\"><div style=\"width: 15%; display: table-cell;\">" . $phraseplugin->param("TXT0003") . "</div>: <span style=\"background-color: #cccccc\">$_\": {\\i\"fwDay\"\\i: \\v</span></li>\n<li style=\"display: table-row;\"><div style=\"width: 15%; display: table-cell;\">" . $phraseplugin->param("TXT0004") . "</div>: <span style=\"background-color: #cccccc\">$_\": {\\i\"wkDay\"\\i: \\v</span></li>\n</ul></p>";
	}
	print $phraseplugin->param("TXT0005") . ": <span style=\"background-color: #cccccc\">\"now\": \\v</span></p>\n";
}

# Load footer and replace HTML Markup <!--$VARNAME--> with perl variable $VARNAME
open(F,"$installfolder/templates/system/$lang/footer.html") || die "Missing template system/$lang/header.html";
  while (<F>) {
    $_ =~ s/<!--\$(.*?)-->/${$1}/g;
    print $_;
  }
close(F);

exit;

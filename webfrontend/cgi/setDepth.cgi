#!/usr/bin/perl

use File::HomeDir;
use CGI qw/:standard/;
use Config::Simple;
use Cwd 'abs_path';
use warnings;
use strict;
no strict "refs"; # we need it for template system

my  $home = File::HomeDir->my_home;
my  $installfolder;
my  $cfg;
my  $conf;
our $psubfolder;
our $depth;
our $namef;
our $value;
our %query;
our $do;

# Read Settings
$cfg             = new Config::Simple("$home/config/system/general.cfg");
$installfolder   = $cfg->param("BASE.INSTALLFOLDER");

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
	
# Figure out in which subfolder we are installed
$psubfolder = abs_path($0);
$psubfolder =~ s/(.*)\/(.*)\/(.*)$/$2/g;

# read caldav4lox configs
$conf = new Config::Simple("$home/config/plugins/$psubfolder/caldav4lox.conf");
$conf->param('general.Depth', $query{'depth'});
$conf->save();

print "Content-Type: text/html\n\n";
print "OK\n";

exit;

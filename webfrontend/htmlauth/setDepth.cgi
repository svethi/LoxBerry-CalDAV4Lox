#!/usr/bin/perl

use LoxBerry::System;
use CGI qw/:standard/;
use warnings;
use strict;
no strict "refs"; # we need it for template system

my  $installfolder;
my  $cfg;
my  $conf;
our $psubfolder;
our $depth;
our $namef;
our $value;
our %query;
our $do;

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

# read caldav4lox configs
$conf = new Config::Simple("$lbpconfigdir/caldav4lox.conf");
$conf->param('general.Depth', $query{'depth'});
$conf->save();

print "Content-Type: text/html\n\n";
print "OK\n";

exit;

#!/usr/bin/env perl

use strict;
use warnings;
use LWP::Curl;

my %config = do 'config.pl';

my $fields = {
    token     => $config{api_token},
    content   => 'log',
    format    => 'json',
    logtype => '',
    user => '',
    record => '',
    beginTime => '10/06/2020 17:37',
    endTime => ''
};

my $ch = LWP::Curl->new(auto_encode => 0);
my $content = $ch->post($config{api_url}, $fields, $config{referer});

print $content;

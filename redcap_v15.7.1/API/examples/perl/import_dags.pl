#!/usr/bin/env perl

use strict;
use warnings;
use LWP::Curl;
use JSON;

my %config = do 'config.pl';

my $record = {
	data_access_group_name => 'Group API',
	unique_group_name    => ''
};

my $json = JSON->new->allow_nonref;
my $data = $json->encode([$record]);

my $fields = {
    token    => $config{api_token},
    content  => 'dag',
	action   => 'import',
    format   => 'json',
    data     => $data,
};

my $ch = LWP::Curl->new(auto_encode => 0);
my $content = $ch->post($config{api_url}, $fields, $config{referer});

print $content;

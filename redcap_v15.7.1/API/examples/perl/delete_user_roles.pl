#!/usr/bin/env perl

use strict;
use warnings;
use LWP::Curl;

my %config = do 'config.pl';

my $fields = {
    token     => $config{api_token},
    content   => 'userRole',
	action    => 'delete',
    format    => 'json',
	'roles[0]' => 'U-522RX7WM49',
};

my $ch = LWP::Curl->new(auto_encode => 0);
my $content = $ch->post($config{api_url}, $fields, $config{referer});

print $content;

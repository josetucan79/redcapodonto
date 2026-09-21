#!/usr/bin/env perl
use strict;
use warnings;
use LWP::Curl;

my %config = do 'config.pl';
my $data = {
    token => $config{api_token},
    action => 'rename',
    content => 'record',
    record => '1',
    new_record_name => 'record_1',
    arm => '1',
    returnFormat => 'json'
};
my $ch = LWP::Curl->new(auto_encode => 0);
my $content = $ch->post(
    $config{api_url},
    $data,
    $config{referer}
);
print $content;
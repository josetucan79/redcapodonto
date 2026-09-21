#!/usr/bin/env perl
use strict;
use warnings;
use LWP::Curl;

my %config = do 'config.pl';
my $data = {
    token => $config{api_token},
    action => 'delete',
    content => 'record',
    'records[0]' => '1',
    arm => '1',
    instrument => 'demographics',
    event => 'visit_1_arm_1',
    returnFormat => 'json'
};
my $ch = LWP::Curl->new(auto_encode => 0);
my $content = $ch->post(
    $config{api_url},
    $data,
    $config{referer}
);
print $content;
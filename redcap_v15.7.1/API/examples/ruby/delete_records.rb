#!/usr/bin/env ruby
require 'json'
require 'curl'
require './settings.rb'
include Settings

fields = {
    :token => Settings::API_TOKEN,
    :action => 'delete',
    :content => 'record',
    'records[0]' => '1',
    :arm => '1',
    :instrument => 'demographics',
    :event => 'visit_1_arm_1',
    :returnFormat => 'json'
}
ch = Curl::Easy.http_post(
   Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str
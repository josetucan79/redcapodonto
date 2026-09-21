#!/usr/bin/env ruby
require 'json'
require 'curl'
require './settings.rb'
include Settings

fields = {
    :token => Settings::API_TOKEN,
    :action => 'rename',
    :content => 'record',
    :record => '1',
    :new_record_name => 'record_1',
    :arm => '1',
    :returnFormat => 'json'
}
ch = Curl::Easy.http_post(
   Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str
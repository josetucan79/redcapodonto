#!/usr/bin/env ruby

require 'json'
require 'curl'
require './settings.rb'
include Settings

fields = {
  :token => Settings::API_TOKEN,
  :content => 'user',
  :action => 'delete',
  :format => 'json',
  'users[0]' => 'test_user_47',
}

ch = Curl::Easy.http_post(
  Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str

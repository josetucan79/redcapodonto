#!/usr/bin/env ruby

require 'json'
require 'curl'
require './settings.rb'
include Settings

fields = {
  :token => Settings::API_TOKEN,
  :content => 'dag',
  :action => 'delete',
  :format => 'json',
  'dags[0]' => 'group_api',
}

ch = Curl::Easy.http_post(
  Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str

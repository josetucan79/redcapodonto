#!/usr/bin/env ruby

require 'curl'
require './settings.rb'
include Settings

fields = {
  :token => Settings::API_TOKEN,
  :content => 'log',
  :format => 'json',
  :logtype => '',
  :user => '',
  :record => '',
  :beginTime => '10/06/2020 17:37',
  :endTime => ''
}

ch = Curl::Easy.http_post(
  Settings::API_URL,
  fields.collect{|k, v| Curl::PostField.content(k.to_s, v)}
)
puts ch.body_str

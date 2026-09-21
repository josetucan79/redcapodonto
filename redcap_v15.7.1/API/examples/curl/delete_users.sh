#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=user&action=delete&format=json&users[0]=test_user_47"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL

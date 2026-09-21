#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=userRole&action=delete&format=json&roles[0]=U-522RX7WM49"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL

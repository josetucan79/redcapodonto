#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=log&format=json&logtype=&user=&record=&beginTime=10/06/2020 17:37&endTime="

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL

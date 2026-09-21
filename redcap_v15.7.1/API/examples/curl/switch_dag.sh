#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=dag&action=switch&format=json&dag=group_api"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL

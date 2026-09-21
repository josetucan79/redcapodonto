#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=dag&action=import&format=json&data=[{\"data_access_group_name\":\"Group%20API\",\"unique_group_name\":\"\"}]"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL

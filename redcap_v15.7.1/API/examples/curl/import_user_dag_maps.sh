#!/bin/sh

. ./config

DATA="token=$API_TOKEN&content=userDagMapping&action=import&format=json&data=[{\"username\":\"testuser\",\"redcap_data_access_group\":\"api_testing_group\"}]"

$CURL -H "Content-Type: application/x-www-form-urlencoded" \
      -H "Accept: application/json" \
      -X POST \
      -d $DATA \
      $API_URL

#!/usr/bin/env Rscript
source('config.R')
library(RCurl)
result <- postForm(
    api_url,
    token=api_token,
    action='rename',
    content='record',
    record='1',
    new_record_name='record_1',
    arm='1',
    returnFormat='json'
)
print(result)
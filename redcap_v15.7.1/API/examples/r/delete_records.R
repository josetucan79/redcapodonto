#!/usr/bin/env Rscript
source('config.R')
library(RCurl)
result <- postForm(
    api_url,
    token=api_token,
    action='delete',
    content='record',
    'records[0]'='1',
    arm='1',
    instrument='demographics',
    event='visit_1_arm_1',
    returnFormat='json'
)
print(result)
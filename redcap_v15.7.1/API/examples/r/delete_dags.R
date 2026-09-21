#!/usr/bin/env Rscript

source('config.R')
library(RCurl)

result <- postForm(
    api_url,
    token=api_token,
    content='dag',
    action='delete',
    'dags[0]'='group_api'
)
print(result)

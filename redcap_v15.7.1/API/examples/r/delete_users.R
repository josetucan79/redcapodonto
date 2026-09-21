#!/usr/bin/env Rscript

source('config.R')
library(RCurl)

result <- postForm(
    api_url,
    token=api_token,
    content='user',
    action='delete',
    'users[0]'='test_user_47'
)
print(result)

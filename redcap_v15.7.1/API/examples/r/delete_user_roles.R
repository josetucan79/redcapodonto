#!/usr/bin/env Rscript

source('config.R')
library(RCurl)

result <- postForm(
    api_url,
    token=api_token,
    content='userRole',
    action='delete',
    'roles[0]'='U-522RX7WM49'
)
print(result)

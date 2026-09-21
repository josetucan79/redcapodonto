#!/usr/bin/env Rscript

source('config.R')
library(RCurl)

result <- postForm(
    api_url,
    token=api_token,
    content='log',
    format='json',
    logtype='',
    user='',
    record='',
    beginTime='10/06/2020 17:37',
    endTime=''
)
print(result)

#!/usr/bin/env Rscript

source('config.R')
library(RCurl)
library(jsonlite)

record <- c(
	data_access_group_name='Group API',
	unique_group_name=''
)

data <- toJSON(list(as.list(record)), auto_unbox=TRUE)

result <- postForm(
    api_url,
    token=api_token,
    content='dag',
	action='import',
    format='json',
    data=data
)
print(result)

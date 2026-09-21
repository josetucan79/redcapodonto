#!/usr/bin/env Rscript

source('config.R')
library(RCurl)
library(jsonlite)

record <- c(
	username='testuser',
	redcap_data_access_group='api_testing_group'
)

data <- toJSON(list(as.list(record)), auto_unbox=TRUE)

result <- postForm(
    api_url,
    token=api_token,
    content='userDagMapping',
	action='import',
    format='json',
    data=data
)
print(result)

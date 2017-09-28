#!/usr/bin/Rscript
library(survival)

args = commandArgs(trailingOnly=TRUE)
survfile = args[1]

cox_pval_thresh = 0.25;  # significance of deviation of cox coeff from 0

survtbl <- read.table(survfile,header=T)

sfit <- survfit(Surv(DTE,Censor) ~ 1 , data=survtbl)
sink("tmp/surv_tmp/survival.txt")
cat(paste(sfit$surv, collapse='\n'))
sink()
sink("tmp/surv_tmp/survtimes.txt")
cat(paste(sfit$time, collapse='\n'))
sink()


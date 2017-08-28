#!/usr/bin/Rscript
library(survival)

# For a given factor:
# 1. Coxph fits coeffs that give relative risk based on continuous labels 
# 2. Use coxph coeffs to get relative risk of samples (predict.coxph)
# 3. Stratify samples into 3 quantiles by rel risk
# 4. Get KM curves for each risk stratum (survfit)
# 5. See if the curves for stratum 1,3 are significantly different (survdiff)
#
# Outputs:	coxph coeffs; relative risks; risk strata 1-3
#			survival curves per factor and strata; diff(1-3) pvalues	 	 
#			Makes a number of .txt files in the directory ./surv_tmp, which must exist!
#

args = commandArgs(trailingOnly=TRUE)
group = args[1]
survfile = args[2]

cox_pval_thresh = 0.25;  # significance of deviation of cox coeff from 0

grpcol = paste("G",group,sep="")

survtbl <- read.table(survfile,header=T)

coxfit <- coxph(Surv(DTE, Censor)~survtbl[,grpcol], data=survtbl)

fit_coeff = summary(coxfit)$coefficients[1,5]  
sink("surv_tmp/coxfit.txt")
cat(fit_coeff)
sink()

#if (fit_coeff > cox_pval_thresh) 
#{
	#print("Factor coefficient insignificant under coxph");
	#quit()
#}
#print(paste("Coxph factor significance p=",fit_coeff))

relRisk <- predict(coxfit, survtbl, type="risk")
survtbl$relrisk <- relRisk

sink("surv_tmp/test.txt")
print(survtbl[,c("DTE","Censor",grpcol,"relrisk"),drop=FALSE], row.names=FALSE)
sink()

quants = quantile(relRisk, c(0.,.3,.7,1.))
rfactor <- cut(relRisk, quants, labels=c(1,2,3),include.lowest = TRUE)

survtbl$rstrata <- as.factor(rfactor)
 
sink("surv_tmp/strata.txt")
print(survtbl[,"rstrata",drop=FALSE])
sink()

# Kaplan-Meier curves, not involving coxph fit
sfit <- survfit(Surv(DTE,Censor) ~ rstrata, data=survtbl)
sink("surv_tmp/survival.txt")
cat(paste(sfit$surv, collapse='\n'))
sink()
sink("surv_tmp/survtimes.txt")
cat(paste(sfit$time, collapse='\n'))
sink()

sdf <- survdiff(Surv(DTE,Censor)~rstrata, data=survtbl, subset = rstrata==1 | rstrata==3)

psurvdiff = 1. - pchisq(sdf$chisq, length(sdf$n)-1)

sink("surv_tmp/survdiff.txt")
cat(psurvdiff)
sink()
#print(paste("stratum 1-3 differential pvalue=",psurvdiff))

print(paste("GROUP",group,"   coxp=",fit_coeff,", survp=",psurvdiff,sep=""))



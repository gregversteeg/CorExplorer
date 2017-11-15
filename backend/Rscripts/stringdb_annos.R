#!/usr/bin/Rscript

library(STRINGdb)
sessionInfo();
stringdb <- STRINGdb$new(version="10",score_threshold=400, species=9606)

args <- commandArgs(trailingOnly = TRUE)
groupdir = args[1];

setwd(groupdir)

pcutoff = .05
LMAX = 40000 # this is limitation of stringdb through R package
ngroups = 200;


glens = array(0, dim=c(ngroups))
ppi = array("", dim=c(ngroups))
ppi_expected = array("",dim=c(ngroups))
ppi_pvalues = array("***",dim=c(ngroups))


# generate list of group files and loop
for (i in 1:ngroups)
{
	filename = paste("./group",i-1,".txt",sep="")
	if (!file.exists(filename))
	{
		next
	}
	print(filename)
	data <- read.table(filename, col.names=c("orig"), nrows=LMAX)
	data_mapped <- stringdb$map(data,"orig",removeUnmappedRows=TRUE)
	write.table(data_mapped,file=paste(i-1,".map.txt",sep=""))

	# get ppi pvalues and cutoff for p > pcutoff
	glens[i] = length(data_mapped$STRING_id)

	# the next function finds the probability to have this  PPI by chance!
	ppie <- stringdb$get_ppi_enrichment(data_mapped$STRING_id)
	if (length(ppie$enrichment) > 0) 
	{
		if (ppie$enrichment < pcutoff) 
		{
			interacts = stringdb$get_interactions(data_mapped$STRING_id);
			write.table(interacts,file=paste(i-1,".ppi.txt", sep=""))
			ppi[i] = length(interacts$from)
			ppi_expected[i] = ppie$lambda
			ppi_pvalues[i] = ppie$enrichment
		}
	} 
	else 
	{
		ppi[i] = 0
		ppi_expected[i] = 0
		ppi_pvalues[i] = 1.
	}
	write.table(data.frame(c(i-1),c(ppi_expected[i]),c(ppi[i]),c(ppi_pvalues[i]),c(glens[i])), 
			file=paste(i-1,".ppi.pval.txt",sep=""),quote=FALSE,sep="\t",col.names=FALSE,row.names=FALSE)


	enrichmentGO <- stringdb$get_enrichment(data_mapped$STRING_id, category="Process",methodMT="fdr",iea=TRUE)
	write.table(enrichmentGO, file=paste(i-1,".GO.txt",sep=""),quote=FALSE,row.names=FALSE,sep="\t")
	glens[i] = length(data_mapped$STRING_id)
	if (glens[i] > 0) 
	{
		enrichmentKEGG <- stringdb$get_enrichment(data_mapped$STRING_id, category="KEGG",methodMT="fdr",iea=TRUE)
		write.table(enrichmentKEGG, file=paste(i-1,".KEGG.txt",sep=""),quote=FALSE,row.names=FALSE,sep="\t")
	}
	else 
	{
		print(paste,i," group has zero length",sep="")
	}
}
write.table(data.frame(c(seq(0,ngroups-1,1)),c(ppi_expected),c(ppi),c(ppi_pvalues),c(glens)), 
		file="stringdb.ppi.txt",quote=FALSE,sep="\t",col.names=FALSE,row.names=FALSE)


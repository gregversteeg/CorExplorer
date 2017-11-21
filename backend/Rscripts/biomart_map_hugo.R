#!/usr/bin/Rscript
library(biomaRt)
mart = useEnsembl(biomart="ensembl", dataset="hsapiens_gene_ensembl")

args = commandArgs(trailingOnly=TRUE)
dir = args[1]

genelist = paste(dir,"genelist.txt",sep="/")
genetbl = paste(dir,"biomart.gene.tbl",sep="/")

gids = readLines(genelist)

res = getBM(c("ensembl_gene_id","external_gene_name","external_gene_source","description","gene_biotype"),
				"ensembl_gene_id",gids,mart);
write.table(res, file=genetbl,quote=FALSE,row.names=FALSE,sep="\t")


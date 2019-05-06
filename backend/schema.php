<?php
include_once("db.php");
#
# Handling of gene names:
# Each distinct experiment will have its own list of gene names, indexed
# by glists.ID.
# Each glists.ID list is used in at least one corex run on the site. 
#
# Gene list is separate from dataset, however it may be that they
# are always in one-to-one correspondence. If so, no problem.
#
# These genes will be mapped onto the Ensembl proteins in order to 
# get PPI data from StringDB.
#
##########################################################################
#
# Gene lists = collections of gene names used for particular experiments
#

$sql = "create table glists (
	ID int primary key auto_increment,
	descr text
);";
if (!table_exists("glists")) { schema_add($sql);}

##########################################################################
#
# Gene list = list of gene names
#

$sql = "create table glist (
	ID int primary key auto_increment,
	GLID int not null, 			# gene list ID
	lbl varchar(30) unique,		# the name as used in the dataset
	hugo varchar(30),			# HGNC or other common name of the gene, if can be determined (BIOMART)
	gtype varchar(30), 			# gene type (BIOMART)
	gsrc varchar(30), 			# source of the gene name (e.g., HGNC) (BIOMART)
	descr text,					# gene description (BIOMART)
	eterm int default 0,		# ENSG number, if any. Redundant but convenient. 
	index(lbl),
	index(eterm),
	index(hugo)
);";
if (!table_exists("glist")) { schema_add($sql);}
if (!table_has_column("glist","GLID"))
{
	dbq("alter table glist add column GLID int after ID");
}


##########################################################################
#
# Gene mapping to ENSP proteins - needed to build PPI graph
# and to map genes to GOs 
#

$sql = "create table g2e (
	GID int not null, 
	term int not null,			# ref eprot
	unique index (GID,term),
	index (term)
);";
if (!table_exists("g2e")) { schema_add($sql);}

$sql = "create table eprot (
	term int not null unique,			
	descr text
);";
if (!table_exists("eprot")) { schema_add($sql);}


##########################################################################
#
# ENSP mapping to GO
#

$sql = "create table e2go (
	eterm int not null,  	# ref eprot
	gterm int not null, 		# ref gos
	unique index (gterm,eterm),
	index (eterm)
);";
if (!table_exists("e2g")) { schema_add($sql);}



##########################################################################
#
# Dataset = collection of samples
#

$sql = "create table dset (   
	ID int primary key auto_increment,
	lbl varchar(50) unique,
	expr_type varchar(20), 			# fpkm, rpkm, etc
	descr text
);";
if (!table_exists("dset")) { schema_add($sql);}

##########################################################################
#
# Sample = a single subject for whom expression was measured (levels are in table expr)
#

$sql = "create table samp (
	ID int primary key auto_increment,
	lbl varchar(50),
	DSID int not null,
	unique index (DSID, lbl)
);";
if (!table_exists("samp")) { schema_add($sql);}

##########################################################################
#
# Sample alternate names
# Introduced for LUAD lung data where samples have both barcodes 
# and TCGA names
# So far NOT USED

$sql = "create table sampalias (
	SID int,
	lbl varchar(50),
	idx int not null,
	unique index (SID,idx) 
);";
if (!table_exists("sampalias")) { schema_add($sql);}

##########################################################################
#
# Sample metadata - data used for survival code
#

$sql = "create table sampdt (
	SID int unique,
	dtd int, 					# days to death, if applicable
	dtlc int, 					# days to loss of contact, if applicable
	dte int,					# dtd or dtlc, as applicable
	stat tinyint not null,		# 1=alive, 0=dead
	censor tinyint not null,	# 1=died, with tumor, during study period
	age smallint default 0,
	sex char default 'U',
	stage tinyint,
	cytored tinyint,
	stagestr varchar(15),
	cytoredstr varchar(30),
	statstr varchar(15),
	tstatstr varchar(30),
	fulldata text 		# a json string, if available
);";
if (!table_exists("sampdt")) { schema_add($sql);}

##########################################################################
#
# Expression levels: sample x gene = level
#

$sql = "create table expr (
	GID int not null,
	SID int not null,
	DSID int not null,		# this is redundant, not sure anything is using it
	raw float not null,	  	# un-normalized
	logz float not null, 	# log2 score, normalized by z
	unique index (DSID,GID,SID)
);";
if (!table_exists("expr")) { schema_add($sql);}

##########################################################################
#
# Clustering runs
#

$sql = "create table clr (
	ID int primary key auto_increment,
	GLID int not null, 				# gene list used for this run
	DSID int not null, 				# sample set used for this run
	meth varchar(30),
	lbl varchar(30) unique,
	param text,						# json of corex params
	descr text,
	ref text,
	projstat text,					# used during loading
	dataurl text,					# origin url for the data - used during web load
	hideme tinyint default 0, 		# if set, project not shown to anyone
	publc tinyint default 0,		# if set, project readable to anyone - if not hidden
	ownedby int not null,			# user who uploaded the project
	projdir text, 					# disk location of project files and log file
	load_dt datetime not null
);";
if (!table_exists("clr")) { schema_add($sql);}

##########################################################################
#
#
# Clusters (= Factors, for CorEx)
#

$sql = "create table clst (
	ID int primary key auto_increment,
	CRID int not null,  		# which clustering run
	lbl int not null, 			# label of the cluster
	lvl smallint not null,   	# level, if hierarchical; level 0 always groups genes
	coxp float default 1,		# significance of coxph survival fit for this group
	survp float default 1,		# survival curve differential pvalue between risk strata 1-3
	tc float default 0, 		# TC value
	unique index(CRID,lvl,lbl)
);";
if (!table_exists("clst")) { schema_add($sql);}

##########################################################################
#
# Gene-to-cluster mapping (level 0)
# Separated from other levels due to reference to GID
#

$sql = "create table g2c (
	GID int not null, 	
	CID int not null, 
	CRID int not null, 			# redund, easier to delete
	wt float not null,   		# link strength
	mi float default 0, 		# mutual information, if corex
	unique index (GID, CID),
	index (CID),
	index (CRID)
);";
if (!table_exists("g2c")) { schema_add($sql);}

##########################################################################
#
# Cluster-to-cluster mapping
# CID1 is a member of CID2
#

$sql = "create table c2c (
	CID1 int not null,
	CID2 int not null, 
	CRID int not null, 			# redund, easier to delete
	wt float not null,
	mi float default 0,
	unique index (CID2,CID1),
	index (CID1)
);";
if (!table_exists("c2c")) { schema_add($sql);}

##########################################################################
#
# Labels
# These are numbers e.g. 0,1,2 assigned by corex for each (sample, factor) pair
# Generally they relate to more/less expression of the gene group for that factor
#

$sql = "create table lbls (
	CID int not null,
	SID int not null, 
	lbl int not null,		# discrete label
	clbl float not null, 	# continuous = log(p0) - log(p2)
	risk_strat int default 0, 	# coxph risk stratum , 0 means not assigned
	unique index (CID,SID),
	index (SID)
);";
if (!table_exists("lbls")) { schema_add($sql);}

##########################################################################
#
# Survival curve data
# There is one curve for each risk stratum

$sql = "create table survdt (
	CID int not null,
	strat int default 0,	# expecting risk srata 1,2,3
	dte int not null,		# time axis - days 
	surv float not null, 	# survival fraction
	index(CID)
);";
if (!table_exists("survdt")) { schema_add($sql);}

##########################################################################
#
# Overall survival curve data
#

$sql = "create table survdt_ov (
	CRID int not null,
	dte int not null,		# time axis - days 
	surv float not null, 	# survival fraction
	index(CRID)
);";
if (!table_exists("survdt_ov")) { schema_add($sql);}

##########################################################################

##########################################################################
#
# Paired survival tables
#

$sql = "create table clst_pair (
	CID1 int not null,
	CID2 int not null,
	coxp float default 1,		
	survp float default 1,
	unique index(CID1,CID2)	
);";
if (!table_exists("clst_pair")) { schema_add($sql);}

##########################################################################
#
# Paired survival curve data
#

$sql = "create table pair_survdt (
	CID1 int not null,
	CID2 int not null,
	strat int default 0,	
	dte int not null,	
	surv float not null,
	index(CID1,CID2,strat)	
);";
if (!table_exists("pair_survdt")) { schema_add($sql);}

##########################################################################
#
# Paired survival sample labels (risk stratum)
#

$sql = "create table pair_lbls (
	CID1 int not null,
	CID2 int not null,
	SID int not null, 
	risk_strat int default 0, 	# coxph risk stratum , 0 means not assigned
	unique index (CID1,CID2,SID),
	index (SID)
);";
if (!table_exists("pair_lbls")) { schema_add($sql);}

#
# GO and Kegg:
# These are loaded separately for each run that is annotated
# so that updates to GO/Kegg cannot cause conflicts between different 
# runs
# Only the terms needed for annotation are loaded

##########################################################################
#
#	GOs
#

$sql = "create table gos (
	ID int primary key auto_increment,
	CRID int not null,
	term int not null, 			# the go number
	descr varchar(100), 		
	unique index (CRID,term),
	index(CRID,descr)
);";
if (!table_exists("gos")) { schema_add($sql);}

##########################################################################
#
#	Kegg
#

$sql = "create table kegg (
	ID int primary key auto_increment,
	CRID int not null,
	term int not null, 			# the number
	descr varchar(100), 			
	unique index (CRID,term),
	index(CRID,descr)
);";
if (!table_exists("kegg")) { schema_add($sql);}

##########################################################################
#
#	GO enrichment of groups
#

$sql = "create table clst2go (
	CID int, 
	term int not null, 			
	pval float not null,  # these are FDR values from stringDB
	index (CID),
	index(term)
);";
if (!table_exists("clst2go")) { schema_add($sql);}

##########################################################################
#
#	Kegg enrichment of groups
#

$sql = "create table clst2kegg (
	CID int, 
	term int not null, 			
	pval float not null,  # these are FDR values from stringDB
	index (CID),
	index(term)
);";
if (!table_exists("clst2kegg")) { schema_add($sql);}

##########################################################################
#
#	PPI links from String
#	We store them redundantly, i.e. both ID1-->ID2 and the reverse
#	so we can get all links on a set with one query
#   This is global to all runs and will be updated as String is updated

$sql = "create table ppi (
	ID1 int, 		# e.g., ENSP00000000233 becomes 233
	ID2 int, 
	score smallint not null, 			
	unique index (ID1,ID2)
);";
if (!table_exists("ppi")) { schema_add($sql);}


######################################################################
#
# Users table
#
$sql = "create table usrs (
	UID int primary key auto_increment,
	usr varchar(30) not null unique,
	passwd varchar(100) not null,		# hashed password
	descr tinytext,  					# what this user is - maybe never need this
	uadmin boolean default 0,			# use is an administrator
	addprj boolean default 1,			# user can load data
	disab boolean default 0,			# user has been disabled
	adddate timestamp default current_timestamp
);";
if (!table_exists("usrs")) 
{ 
	schema_add($sql);
}

######################################################################
#
# Access table  users x projects
#
$sql = "create table access (
	UID int not null,
	CRID int not null,
	wrt boolean default 1     # has write access
);";
if (!table_exists("access")) 
{ 
	schema_add($sql);
}
##############################################################################

function schema_add($sql)
{
	global $DB;
	$res = $DB->query($sql);
	if (!$res)
	{
		print $DB->error;
	}
}


?>

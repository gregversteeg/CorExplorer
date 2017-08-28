<?php
require_once("db.php");

#
# Load the gene table that was built by build_gene_table. 
# This makes the ENSP and GO assignments. 
# For the moment, this is loaded AFTER the project is already loaded. 
# But, this could be used instead of the genelist during the initial load. 
# 

#$GLID = 1;
#$CRID = 1;
#$table_file = "/local/wnelson/disk/for_will_shrinkage2_all_files_june_2017/shrinkage.genes.tsv";

$GLID = 3;
$CRID = 4;
$table_file = "/local/wnelson/disk/bLUAD/lung.genes.tsv";

# Get all the genes in the project so we can have their IDs
$gids = array();
$res = dbq("select ID, lbl from glist where glid=$GLID");
while ($r = $res->fetch_array())
{
	$gids[$r["lbl"]] = $r["ID"];
}

$fh = fopen($table_file,"r");
$g2hugo = array();
$g2desc = array();
$g2ensps = array();
while (($line = fgets($fh)) != false)
{
	$fields = explode("\t",$line);
	$gene = $fields[0];
	$hugo = $fields[1];
	$desc = $fields[2];
	$ensps = $fields[3];
	
	if (!isset($gids[$gene]))
	{
		die("unknown gene $gene\n");
	}
	$gid = $gids[$gene];

	$g2hugo[$gid] = $hugo;
	$g2desc[$gid] = $desc;
	$g2ensps[$gid] = $ensps;
}
fclose($fh);

print("clear out the prior GO and ENSP mappings\n");
foreach ($gids as $lbl => $gid)
{
	dbq("delete from g2e where gid=$gid");
	dbq("delete from g2g where gid=$gid");
}

$gid = 0;
$desc = "";
$hugo = "";
$st = $DB->prepare("update glist set descr=?,hugo=? where ID=? ");
$st->bind_param("ssi",$desc,$hugo,$gid);	
foreach ($g2hugo as $gid => $hugo)
{
	$ensps = $g2ensps[$gid];
	$desc = $g2desc[$gid];

	print "$gid\t$hugo\t$ensps        \r";

	$st->execute();

	if (trim($ensps) == "")
	{
		continue;
	}
	$earr = explode(",",$ensps);
	foreach ($earr as $eterm)
	{
		dbq("insert ignore into g2e (GID,term) values ($gid,$eterm)");
		dbq("insert ignore into g2g (GID,term) ".
				"(select $gid,gterm from esp2go where eterm=$eterm)");
		dbq("insert ignore into gos (CRID,term,descr) ".
			"(select $CRID,gterm,descr from esp2go ".
			"join global_gos on global_gos.term=esp2go.gterm where esp2go.eterm=$eterm)");
	}	
}

?>

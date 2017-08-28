<?php
require_once("db.php");

#
# Starting from one of the corex files, get the list of genes
# and use stored mappings to fill out a table as follows:
#
# <Gene name in project> <Hugo name> <Description> <ENSPs> <GOs>
#
# If the previously-loaded mappings don't suffice to fill out this table,
# then extra work will have to be done to complete it before loading
# the project. 
# IF AT ALL POSSIBLE, TRY TO MAP PROJECT GENE NAMES TO HUGO NAMES AND 
# LOAD THAT MAPPING INTO THE DATABASE. Then use this script to construct the table. 
#

$matrix_file = "/local/wnelson/disk/bLUAD/lung/text_files/weights_layer0.csv";
#$matrix_file = "/local/wnelson/disk/for_will_shrinkage2_all_files_june_2017/output_folder/text_files/weights_layer0.csv";

$fh = fopen($matrix_file,"r");
$line = fgets($fh);
fclose($fh);

$genes = explode(",",trim($line));
array_shift($genes); # first entry is "factor"

# get our stored mappings

$hugoID = array();
$hugo_desc = array();
$ID2hugo = array();
$res = dbq("select ID, lbl, descr from hugo_lbl");
while ($r = $res->fetch_assoc())
{
	$lbl =	$r["lbl"];
	$ID = 	$r["ID"];
	$desc = $r["descr"];
	$hugoID[strtolower($lbl)] = $ID;
	$ID2hugo[$ID] = $lbl;   # keep this the original case so we don't get all lowers in the end
	$hugo_desc[$ID] = $desc;
}
$res = dbq("select HID, lbl from map2hugo");
while ($r = $res->fetch_assoc())
{
	$hugoID[strtolower($r["lbl"])] = $r["HID"];
}

# ENSP-to-GO : we need this to map the genes to GO
$ensp2go = array();
$res = dbq("select eterm, gterm from esp2go");
while ($r = $res->fetch_assoc())
{
	$gterm = $r["gterm"];
	$eterm = $r["eterm"];
	if (!isset($ensp2go[$eterm]))
	{
		$ensp2go[$eterm] = $gterm;
	}
	else
	{
		$ensp2go[$eterm] .= ",$gterm";
	}
}
 
# Hugo-to-ENSP
$h2ensp = array();
$res = dbq("select HID, term from hugo2esp");
while ($r = $res->fetch_assoc())
{
	$eterm = $r["term"];
	$HID = $r["HID"];
	if (!isset($h2ensp[$HID]))
	{
		$h2ensp[$HID] = $eterm;
	}
	else
	{
		$h2ensp[$HID] .= ",$eterm";
	}
}

# Print the table!

foreach ($genes as $gene)
{
	$desc = "";
	$ensps = "";
	$gos = "";
	$hugo = "";
	if (isset($hugoID[strtolower($gene)]))
	{
		$HID = $hugoID[strtolower($gene)];
		$desc = $hugo_desc[$HID];
		$hugo = $ID2hugo[$HID];
		if (strtolower($hugo) == strtolower($gene))
		{
			$gene = $hugo; # prefer to keep the same case
		}
		if (isset($h2ensp[$HID]))
		{
			$ensps = $h2ensp[$HID];
		}
		$earr = explode(",",$ensps);
		$garr = array();
		foreach ($earr as $eterm)
		{
			if (isset($ensp2go[$eterm]))
			{
				$garr = array_merge($garr,explode(",",$ensp2go[$eterm]));	
			}
		}
		$gos = implode(",",$garr);
	}	
	print "$gene\t$hugo\t$desc\t$ensps\n";
}

?>

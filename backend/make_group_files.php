<?php
require_once("db.php");

#
# Make the files 'groupN.txt' for the level 1 groups
# These are just lists of proteins (ENSP)
# Used by the R script (stringdb_annos.R) to do enrichment, etc.
#

$annodir = "/local/wnelson/disk/bLUAD/lung/annos";
$CRID = 4;
$minMI = .002;

#$annodir = "/local/wnelson/disk/LUAD/lung/annos";
#$CRID = 3;
#$minMI = .000000001;

#$annodir = "/local/wnelson/disk/jul_2015_ExpressionData/Code/annotations/new_annos2";
#$CRID = 1;
#$minMI = .002;

$cids = array();
$res = dbq("select ID,lbl from clst where CRID=$CRID and lvl=0");
while ($r = $res->fetch_assoc())
{
	$cid = $r["ID"];
	$lbl = $r["lbl"];
	$cids[$lbl] = $cid;
}

$numFiles = 0;
foreach ($cids as $lbl => $cid)
{
	$grpfile = "$annodir/group$lbl.txt";
	$names = array();
	$res = dbq("select distinct term from g2e join g2c on g2c.GID=g2e.GID ".
			" where g2c.CID=$cid and g2c.mi >= $minMI order by g2c.mi desc");
	while ($r = $res->fetch_assoc())
	{
		$term = $r["term"];
		$names[] = ensp_name($term);
	}
	if (count($names) > 0)
	{
		print "$grpfile                            \r";
		$numFiles++;
		file_put_contents($grpfile,implode("\n",$names)."\n");
	}
}
print "$numFiles created                                 \n";


function ensp_name($num)
{
	# return name of form ENSP00000323929
	while (strlen($num) < 11)
	{
		$num = "0$num";
	}	
	return "ENSP$num";
}
?>

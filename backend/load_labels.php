<?php
require_once("util.php");
require_once("db.php");

# Load discrete and continuous labels
# These are corex outputs with a consistent format, 
#	row = <sample name>,group_0, group_1, ....
#

#$crid = 1;
#$dsid = 1;
#$label_file = "/local/wnelson/disk/for_will_shrinkage2_all_files_june_2017/output_folder/text_files/labels.txt";
#$clabel_file = "/local/wnelson/disk/for_will_shrinkage2_all_files_june_2017/output_folder/text_files/cont_labels.txt";

$crid = 4;
$dsid = 3;
$label_file = "/local/wnelson/disk/bLUAD/lung/text_files/labels.txt";
$clabel_file = "/local/wnelson/disk/bLUAD/lung/text_files/cont_labels.txt";

#
# Get the sample and lowest-layer cluster IDs 
#

$samp2ID = array();
$res = dbq("select id,lbl from samp where dsid=$dsid");
while ($r = $res->fetch_assoc())
{
	$sid = $r["id"];
	$lbl = $r["lbl"];
	$samp2ID[$lbl] = $sid;
}
$numSamp = count($samp2ID);

$grp2ID = array();
$res = dbq("select id,lbl from clst where crid=$crid and lvl=0");
while ($r = $res->fetch_assoc())
{
	$cid = $r["id"];
	$lbl = $r["lbl"];
	$grp2ID[$lbl] = $cid;
}
$numGrp = count($grp2ID);

print "Loaded $numSamp samples, $numGrp groups\n";

$labels = array();
$clabels = array();

$nRows = 0;
$nCols = 0;

read_matrix($labels, $nRows, $nCols, $label_file,0);
if ($nRows != $numSamp)
{
	die ("label file has $nRows rows!");
}
if ($nCols != $numGrp+1)
{
	die ("label file has $nCols cols!");
}

read_matrix($clabels, $nRows, $nCols, $clabel_file,0);
if ($nRows != $numSamp)
{
	die ("cont_label file has $nRows rows!");
}
if ($nCols != $numGrp+1)
{
	die ("cont_label file has $nCols cols!");
}

for ($r = 0; $r < $numSamp; $r++)
{
	$samp1 = $labels[$r][0];
	if (!isset($samp2ID[$samp1]))
	{
		die ("unknown sample $samp1 in label file\n");
	}
	$samp2 = $clabels[$r][0];
	if (!isset($samp2ID[$samp2]))
	{
		die ("unknown sample $samp2 in clabel file\n");
	}
	if ($samp1 != $samp2)
	{
		die ("mismatch samples, labels has $samp1, clabel has $samp2\n");
	}
}
for ($g = 0; $g < $numGrp; $g++)
{
	if (!isset($grp2ID[$g]))
	{
		die ("No ID for group $g\n");
	}
}

dbq("delete lbls.* from lbls,clst where lbls.CID=clst.ID and clst.CRID=$crid");
$N = $numSamp;
for ($r = 0; $r < $numSamp; $r++)
{
	$samp = $labels[$r][0];
	$SID = $samp2ID[$samp];
	$vals = array();
	for ($g = 0; $g < $numGrp; $g++)
	{
		$CID = $grp2ID[$g];
		$lbl = $labels[$r][$g + 1];
		$clbl = $clabels[$r][$g + 1];
		$vals[] = "($SID,$CID,$lbl,$clbl)";
	}	
	print "$N\t\t$samp                             \r";
	dbq("insert into lbls (SID,CID,lbl,clbl) values".implode(",",$vals));
	$N--;
}







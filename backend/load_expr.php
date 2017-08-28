<?php
require_once("util.php");
require_once("db.php");

# Load expression data 

#$ignore_unknown_genes = 0;
#$transpose = 1;   # if the matrix has genes across the top, we need to transpose
#$dsid = 1;
#$glid = 1;
#$raw_file = "/local/wnelson/disk/jul_2015_ExpressionData/matrix.tcga_ov.geneset1.RPKM.txt";

$ignore_unknown_genes = 1;  # in case the expr matrix has genes not used in corex clustering
$transpose = 0; 
$dsid = 3;
$glid = 3;
$raw_file = "/local/wnelson/disk/bLUAD/LUAD_FPKM-UQ.csv";

$gname2ID = array();
$res = dbq("select id,lbl from glist where glid=$glid");
while ($r = $res->fetch_assoc())
{
	$gid = $r["id"];
	$lbl = $r["lbl"];
	$gname2ID[$lbl] = $gid;
}
$numGene = count($gname2ID);

$samp2ID = array();
$res = dbq("select id,lbl from samp where dsid=$dsid");
while ($r = $res->fetch_assoc())
{
	$sid = $r["id"];
	$lbl = $r["lbl"];
	$samp2ID[$lbl] = $sid;
}
$numSamp = count($samp2ID);
print "Project has $numGene genes and $numSamp samples\n";

$nRows = 0;
$nCols = 0;
$matrix = array();
print "Reading matrix: $raw_file\n";
read_matrix($matrix,$nRows, $nCols,$raw_file);

print "Deleting existing matrix\n";
dbq("delete from expr where DSID=$dsid");

print "Loading matrix\n";

if ($transpose)
{
	$matrix = transpose_matrix($matrix);
	$nRows = count($matrix);
	$nCols = count($matrix[0]);
}

# should have samples across the top, genes going down
if ($nCols != $numSamp+1)
{
	print ("$numSamp samples but $nCols columns in matrix!\n");
}
if ($nRows != $numGene+1)
{
	print ("$numGene genes but $nRows rows in matrix!\n");
}

$col2SID = array();
for ($c = 1; $c < $nCols; $c++)
{
	$samp = $matrix[0][$c];
	if (!isset($samp2ID[$samp]))
	{
		die ("Unknown sample '$samp'\n");
	}
	$col2SID[$c] =  $samp2ID[$samp];
}

for ($r = 1; $r < $nRows; $r++)
{
	$gene = $matrix[$r][0];
	if (!isset($gname2ID[$gene]))
	{
		if (!$ignore_unknown_genes)
		{
			die ("unkown gene $gene\n");
		}
		continue;
	}
	print "$r\t\t$gene                              \n";	

	$GID = $gname2ID[$gene];
	$varray = array();
	for ($c = 1; $c < $nCols; $c++)
	{
		$SID = $col2SID[$c];
		$val = $matrix[$r][$c];
		if (!is_numeric($val))
		{
			die ("Bad value:'$val' for $gene,column=$c\n");
		}
		array_push($varray,"($SID,$GID,$dsid,$val)");
	}
	dbq("insert into expr (SID,GID,DSID,raw) values".implode(",",$varray));
}


?>


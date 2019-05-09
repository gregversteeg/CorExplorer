<?php
require_once("util.php");
require_once("db.php");

# Load expression data 

$projname = $argv[1];
$raw_file = $argv[2];

$proj_info = array();
load_proj_data2($proj_info,$projname);
$dsid = $proj_info["DSID"];
$glid = $proj_info["GLID"];

$ignore_unknown_genes = 1;  # in case the expr matrix has genes not used in corex clustering
$transpose = 0; 

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
clear_expr_by_DS($dsid);

if ($transpose)
{
	print "Transposing matrix; could be slow\n";
	$matrix = transpose_matrix($matrix);
	$nRows = count($matrix);
	$nCols = count($matrix[0]);
}

# should have genes across the top, samples going down
if ($nRows != $numSamp+1)
{
	print ("$numSamp samples but $nRows rows in matrix!\n");
}
if ($nCols != $numGene+1)
{
	print ("$numGene genes but $nCols columns in matrix!\n");
}

$col2GID = array();
for ($c = 1; $c < $nCols; $c++)
{
	$gene = $matrix[0][$c];
	if (!isset($gname2ID[$gene]))
	{
		if (!$ignore_unknown_genes)
		{
			die ("unkown gene $gene\n");
		}
		continue;
	}
	$col2GID[$c] =  $gname2ID[$gene];
}

print "Loading matrix to DB\n";

for ($r = 1; $r < $nRows; $r++)
{
	$samp = $matrix[$r][0];
	if (!isset($samp2ID[$samp]))
	{
		die ("unkown sample $samp\n");
	}
	print "Loading row $r/$nRows                              \r";	
	flush();

	$SID = $samp2ID[$samp];
	$varray = array();
	for ($c = 1; $c < $nCols; $c++)
	{
		if (isset($col2GID[$c]))
		{
			$GID = $col2GID[$c];
			$val = $matrix[$r][$c];
			if (!is_numeric($val))
			{
				die ("Bad value:'$val' for $samp,column=$c\n");
			}
			array_push($varray,"($SID,$GID,$dsid,$val,0)");
		}
	}
	dbq("insert into expr (SID,GID,DSID,raw,logz) values".implode(",",$varray));
}

print "Fill logz values\n";

$remaining = $numGene;
foreach ($gname2ID as $name => $gid)
{
	$numvals = 0;
	$sum = 0;
	$sqsum = 0;
	$res = dbq("select raw from expr where gid=$gid and dsid=$dsid");
	while ($r = $res->fetch_assoc())
	{
		$val = 1 + $r["raw"];
		$val = log($val);
		$numvals++;
		$sum += $val;	
		$sqsum += $val*$val;	
	}
	if ($numvals == 0)
	{
		print "No expr for gid=$gid, dsid=$dsid!!\n";
		continue;
	}
	$avg = ((float)$sum)/((float)$numvals);
	$sqavg = ((float)$sqsum)/((float)$numvals);
	$var = $sqavg - $avg*$avg;
	$std = sqrt($var);

	if ($remaining % 100 == 0)
	{
		print "$gid\t\t$remaining\t\t$avg\t\t$std                     \r";
		flush();
	}

	#print("update expr set logz=((log(1+raw) - $avg)/$std) where gid=$gid and dsid=$dsid"\n);
	dbq("update expr set logz=((log(1+raw) - $avg)/$std) where gid=$gid and dsid=$dsid");
	$remaining--;
}	

?>


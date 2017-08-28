<?php
require_once("util.php");
require_once("db.php");

# Compute logz values for expression data (used in heat maps)

$dsid = 3;
$glid = 3;


$gids = array();
$res = dbq("select id from glist where glid=$glid order by id asc");
while ($r = $res->fetch_assoc())
{
	$gids[] = $r["id"];
}

$numGenes = count($gids);
print "$numGenes genes in dataset\n";
foreach ($gids as $gid)
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
	$avg = ((float)$sum)/((float)$numvals);
	$sqavg = ((float)$sqsum)/((float)$numvals);
	$var = $sqavg - $avg*$avg;
	$std = sqrt($var);

	print "$gid\t\t$numGenes\t\t$avg\t\t$std                     \n";

	#print("update expr set logz=((log(1+raw) - $avg)/$std) where gid=$gid and dsid=$dsid"\n);
	dbq("update expr set logz=((log(1+raw) - $avg)/$std) where gid=$gid and dsid=$dsid");
	$numGenes--;
}	

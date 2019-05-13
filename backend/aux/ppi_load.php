<?php
# 
# Load the ppi links that were downloaded from StringDB
#
require_once("../db.php");

$linkfile = "../stringdb_files/9606.protein.links.v11.0.txt";

dbq("delete from ppi");
$fh = fopen($linkfile,"r");
$line = fgets($fh);
$seen = array();
$st = $DB->prepare("insert ignore into ppi (ID1,ID2,score) values(?,?,?)");
$st->bind_param("iis",$ens1,$ens2,$score);	
dbq("start transaction");
$added = 0;
$total = 0;
$Nseen = 0;
while (($line = fgets($fh)) != false) 
{
	if (!preg_match('/\S/',$line))
	{
		continue;
	}
	$fields = preg_split('/\s+/',$line);
	$ens1 = $fields[0];
	$ens2 = $fields[1];
	$score = $fields[2];   # NB they  used to have many more fields
	$ens1 = preg_replace('/9606\.ENSP0*/','',$ens1);
	$ens2 = preg_replace('/9606\.ENSP0*/','',$ens2);
	if (!is_numeric($ens1) || !is_numeric($ens2) || !is_numeric($score) || $ens1==$ens2)
	{
		die ("bad vals $ens1,$ens2,$score\n$line\n");
	}
	# put them in order so we can be sure we're getting each pair
	# in their exactly once in each order
	if ($ens1 > $ens2)
	{
		$tmp = $ens1;
		$ens1 = $ens2;
		$ens2 = $tmp;
	}
#	if (isset($seen[$ens1][$ens2]))
#	{
#		#print "seen:$ens1,$ens2\n";
#		$Nseen++;
#	}
#	$seen[$ens1][$ens2] = 1;

	$st->execute();
	$added++;

	$tmp = $ens1;
	$ens1 = $ens2;
	$ens2 = $tmp;

	$st->execute();
	$added++;

	if ($added >= 1000)
	{
		dbq("commit");
		dbq("start transaction");
		$total += $added;
		$added=0;
		print "$total         \r";
	}
}
if ($added > 0)
{
	dbq("commit");
}
print "seen:$Nseen               \n";

?>


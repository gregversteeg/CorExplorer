<?php
require_once("../db.php");

#
# Load ALL ENSP names and descriptions from the StringDB file
#

$alias_file = "../stringdb_files/9606.protein.info.v11.0.txt";
$fh = fopen("$alias_file","r");
$st = $DB->prepare("insert ignore into eprot (term,descr) values(?,?)");
$st->bind_param("is",$term,$descr);	
dbq("start transaction");
$added = 0;
$total = 0;
while (($line = fgets($fh)) != false) 
{
	$fields = preg_split('/\t/',$line);
	if (count($fields) < 3) 
	{
		continue;
	}
	$term = preg_replace('/9606.ENSP0*/','',$fields[0]);	
	$descr = $fields[2];
	$st->execute();
	$added++;
	if ($added >= 100)
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
fclose($fh);



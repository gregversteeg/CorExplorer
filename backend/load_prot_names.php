<?php
require_once("db.php");

#
# Load ALL ENSP names and descriptions from the StringDB file
#

$alias_file = "/local/wnelson/disk/stringdb_files/9606.protein.aliases.v10.5.txt";
$fh = fopen("$alias_file","r");
$term_seen = array();
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
	if (preg_match('/^Ensembl_HGNC_Approved_Name/',$fields[2]) ||
	 	preg_match('/^BLAST_UniProt_DE/',$fields[2]))
	{
		$term = preg_replace('/9606.ENSP0*/','',$fields[0]);	
		if (isset($term_seen[$term]))
		{
		#	print "repeated:$term\n";
		}
		$term_seen[$term] = 1;
		$descr = $fields[1];
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
}
if ($added > 0)
{
	dbq("commit");
}
fclose($fh);



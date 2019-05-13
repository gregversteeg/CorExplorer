<?php
require_once("../db.php");

$stringdb_mapping_file = "../stringdb_files/human.GO_2_string.2018.tsv";

$st = dbps("insert into e2go (eterm,gterm) values(?,?)");
$st->bind_param("ii",$eterm,$gterm);
$fh = fopen($stringdb_mapping_file,"r");
fgets($fh);
$count = 0;
while ( ($line = fgets($fh)) != null)
{
	$fields = explode("\t",trim($line));
	$gostr = trim($fields[2]);
	$enspstr = trim($fields[3]);

	$gterm = preg_replace("/GO:0*/","",$gostr);
	$eterm = preg_replace("/9606\.ENSP0*/","",$enspstr);

	#print "$gterm\t$eterm\n";
	$count++;
	if ($count % 100 == 0)
	{
		print "$count       \r";
	}

	$st->execute();
	if ($st->errno)
	{
		print($st->error."\n");
	}
}
$st->close();

?>



?>

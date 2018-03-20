<?php
require_once("util.php");

$projname = $argv[1];
$metadata_file = $argv[2];

$proj_info = array();
load_proj_data2($proj_info,$projname);

$CRID = $proj_info["ID"];
$DSID = $proj_info["DSID"];
$GLID = $proj_info["GLID"];

$samp2ID = array();
$res = dbq("select lbl, ID from samp where DSID=$DSID");
while ($r = $res->fetch_assoc())
{
	$samp2ID[$r["lbl"]] = $r["ID"];
}

check_file($metadata_file);

$fh = fopen($metadata_file,"r");
$numerrors = 0;
$goodSamps = array();
print "Read metadata from tsv\n";
while (($line = fgets($fh)) != null)
{
	if (preg_match('/SampleID/i',$line) && preg_match('/DTD/i',$line))
	{
		continue; # header
	}
	if (!preg_match('/\S/',$line))
	{
		continue; # tolerate empty lines
	}
	$fields = explode("\t",trim($line));	
	if (count($fields) != 4)
	{
		print "WARNING: bad metadata line\n$line\n";	
		$numerrors++;
	}
	$samp = $fields[0];
	if (!isset($samp2ID[$samp]))
	{
		print("WARNING: skipping unknown sample $samp\n");
		continue; 
	}
	if ($numerrors == 5)
	{
		print "Too many problems; giving up\n";
	}
	$goodSamps[] = $fields;	
}
fclose($fh);
print "Load metadata to DB\n";
dbq("delete sampdt.* from sampdt,samp where samp.id=sampdt.sid ".
			"  and samp.dsid=$DSID ");

$numSamp = count($samp2ID);
$numData = count($goodSamps);
if ($numData != $numSamp)
{
	print "WARNING: $numSamp samples in project but $numData metadata records found\n";
}
$st = dbps("insert into sampdt (sid,dtd,dtlc,dte,stat,censor) values(?,?,?,?,?,?)");
$st->bind_param("iiiiii",$sid,$dtd,$dtlc,$dte,$statflag,$censor);

foreach ($goodSamps as $dt)
{
	$samp 	= $dt[0];
	$dtd 	= $dt[1];
	$dtlc 	= $dt[2];
	$statflag	= $dt[3];

	$sid = $samp2ID[$samp];

	$censor = ($statflag==1 ? 0 : 1);

	if ($dtd == "")
	{
		$dtd = -1;
	}
	if ($dtlc == "")
	{
		$dtlc = -1;
	}

	$dte = ($statflag ? $dtlc : $dtd);
	if ($dte < 0 && $dtd > 0)
	{
		die("Sample $samp: invalid dtd/dte");
	}
	$st->execute();
}
$st->close();

?>

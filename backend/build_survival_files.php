<?php
require_once("util.php");
require_once("db.php");

#
# Using sample data from DB, build the files needed
# to do survival computation. 
#	In fact there is only one file, with columns:
#	<Sample>	Time	Status	G0	G1...
#
#	Time = DTD or DTLC
#	Status = censored(1), uncensored(0)
#		where censored means died, with tumor
#	G0 etc = continuous factor label 
#

$dsid = 3;
$crid = 4;
$outfile = "lung/Rdata.tsv";

#$dsid = 1;
#$crid = 1;
#$outfile = "shrinkage2/Rdata.tsv";

# get expected number of groups so we can sanity check the table
$numGrp = 0;
$res = dbq("select max(lbl) as maxlbl from clst where crid=$crid and lvl=0");
$r = $res->fetch_assoc();
$numGrp = $r["maxlbl"] + 1;

$conts = array();
$sid2sname = array();
$res = dbq("select samp.lbl as sname, samp.id as sid, clst.lbl as cnum, clst.id as cid, ".
			" lbls.clbl as clbl from lbls join clst on clst.id=lbls.cid ".
			" join samp on samp.id=lbls.sid where clst.crid=$crid ");
while ($r = $res->fetch_assoc())
{
	$sname 	= $r["sname"];
	$sid 	= $r["sid"];
	$cnum 	= $r["cnum"];
	$cid 	= $r["cid"];
	$clbl 	= $r["clbl"];

	if (!isset($conts[$sid]))
	{
		$conts[$sid] = array();
		$sid2sname[$sid] = $sname;
	}
	$conts[$sid][$cnum] = $clbl;
}
foreach ($conts as $sid => $vals)
{
	for ($g = 0; $g < $numGrp; $g++)
	{
		if (!isset($vals[$g]))
		{
			die ("Failed to load group $g for sample $sid\n");
		}
	}
}
# get the samps with valid survival info (signalled by dte > 0)
$sampdt = array();
$res = dbq("select sid, dte, censor from sampdt join samp on samp.id=sampdt.sid ".
		" where samp.dsid=$dsid and dte > 0");
while ($r = $res->fetch_assoc())
{
	$sid 	= $r["sid"];
	$dte 	= $r["dte"];
	$censor 	= $r["censor"];

	$sampdt[$sid] = array("dte" => $dte, "censor" => $censor);
}

$outstr = "SID\tDTE\tCensor";
for ($g = 0; $g < $numGrp; $g++)
{
	$outstr .= "\tG$g";
}
$outstr .= "\n";

foreach ($sampdt as $sid => $dtvals)
{
	if (!isset($conts[$sid]))
	{
		die ("no cont lbls for sid=$sid!\n");
	}
	$sname = $sid2sname[$sid];
	
	$outstr .= "$sname\t$sid\t".$dtvals["dte"]."\t".$dtvals["censor"]."\t";
	$outstr .= implode("\t",$conts[$sid]);
	$outstr .= "\n";
}
file_put_contents($outfile,$outstr);

?>


<?php
require_once("util.php");
require_once("db.php");

#
# Load shrinkage2 sample metadata as used for survival code
# Probably won't work for any other sample set!
#


$dsid = 1;
$data_file = "/local/wnelson/jul_2015_ExpressionData/Metadata/nationwidechildrens.org_clinical_patient_ov.txt";

dbq("delete sampdt.* from sampdt,samp where sampdt.sid=samp.id and samp.dsid=$dsid");

$samp2ID = array();
$res = dbq("select id,lbl from samp where dsid=$dsid");
while ($r = $res->fetch_assoc())
{
	$sid = $r["id"];
	$lbl = $r["lbl"];
	# drop the -01 and -02 since they aren't present in the metadata file
	$lbl = preg_replace('/...$/','',$lbl);
	$samp2ID[$lbl] = $sid;
}
$numSamp = count($samp2ID);

$data = array();
$nRows = 0;
$nCols = 0;
$fh = fopen($data_file,"r");

$header = fgets($fh);
fgets($fh);
fgets($fh);

$harray = explode("\t",trim($header));
$head2col = array();
for ($c = 0; $c < count($harray); $c++)
{
	$headstr = trim($harray[$c]);
	$head2col[$headstr] = $c;
}
$ddt_col = $head2col["death_days_to"];
$lcdt_col = $head2col["last_contact_days_to"];
$cstage_col = $head2col["clinical_stage"];
$rdindex_col = $head2col["residual_disease_largest_nodule"];
$age_col = $head2col["age_at_initial_pathologic_diagnosis"];
$stat_col = $head2col["vital_status"];
$tstat_col = $head2col["tumor_status"];

$stages = array('Normal' => 0,'IA' => 1,'IB' => 2,'IC' => 3,'IIA' => 4,
			'IIB' => 5,'IIC' => 6,'IIIA' => 7,'IIIB' => 8,'IIIC' => 9,'IV' => 10);
$residuals = array('No Macroscopic disease' => 0,'1-10 mm' => 1,'11-20 mm' => 2,'>20 mm' => 3);

$foundSamps = array();
while (($line = fgets($fh)) != false) 
{
	$fields = explode("\t",trim($line));
	$samp = trim($fields[0]);
	$stat = trim($fields[$stat_col]);
	$tstat = trim($fields[$tstat_col]);
	$dtd = trim($fields[$ddt_col]);
	$dtlc = trim($fields[$lcdt_col]);
	$age = trim($fields[$age_col]);
	$stage = trim($fields[$cstage_col]);
	$resid = trim($fields[$rdindex_col]);
	$age = trim($fields[$age_col]);

	if (!is_numeric($dtd))
	{
		$dtd = -1;
	}
	if (!is_numeric($dtlc))
	{
		$dtlc = -1;
	}

	$stage = preg_replace('/Stage\s*/','',$stage);

	if (isset($samp2ID[$samp]))
	{
		$foundSamps[$samp] = 1;
		$sid = $samp2ID[$samp];

		$censor = ($stat == "Dead" && $tstat == "WITH TUMOR" ? 1 : 0);
		$dte = ($stat == "Dead"  ? $dtd : $dtlc);
		$statint = ($stat == "Dead"  ? 0 : 1);
		$stageint = (isset($stages[$stage]) ? $stages[$stage] : -1);
		$resint = (isset($residuals[$resid]) ? $residuals[$resid] : -1);
		
		dbq("insert into sampdt (SID,dtd,dtlc,dte,stat,censor,age,stage,cytored,stagestr,".
				 "cytoredstr,statstr,tstatstr) values($sid,$dtd,$dtlc,$dte,$statint,$censor,".
				"$age,$stageint,$resint,$stageint,'$resid','$stat','$tstat')");
	}
}
fclose($fh);

foreach ($samp2ID as $samp => $ID)
{
	if (!isset($foundSamps[$samp]))
	{
		print "Missing: *$samp*\n";
	}
}

<?php
require_once("util.php");

$projname = $argv[1];
$metadata_json_file = $argv[2];

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

check_file($metadata_json_file);
$sampstr = file_get_contents($metadata_json_file);
$sampdt = json_decode($sampstr); 

dbq("delete sampdt.* from sampdt,samp where samp.id=sampdt.sid ".
			"  and samp.dsid=$DSID ");

$numSamp = count($samp2ID);

$data = array();
$numBC = 0;
foreach ($sampdt as $dt)
{
	$numBC++;
	$bc = preg_replace('/\.FPKM.*$/','',$dt->file_name);
	if (!isset($samp2ID[$bc]))
	{
		die("unknown $bc\n");
	}
	$sid = $samp2ID[$bc];

	$dtd = "";
	$dtlc = "";
	$age = "";
	$status = "alive";

	# get the TCGA name which we will load as an alias
	$ae = array_shift($dt->associated_entities);
	$esi = $ae->entity_submitter_id;
	if (isset($dt->cases) && isset($dt->cases[0]->diagnoses))
	{
		$surv_info = $dt->cases[0]->diagnoses[0];
		$status = strtolower($surv_info->vital_status);
		$dtd = trim($surv_info->days_to_death);
		$dtlc = trim($surv_info->days_to_last_follow_up);
		$age = trim($surv_info->age_at_diagnosis);
	}
	else
	{
		print "WARNING: Sample $bc: invalid cases section\n";
		print "############################################\n";
		print_r($dt);
		print "############################################\n";
	}

	$statflag = ($status == "alive" ? 1 : 0);
	$censor = ($statflag==1 ? 0 : 1);

	if ($dtd == "")
	{
		$dtd = -1;
	}
	if ($dtlc == "")
	{
		$dtlc = -1;
	}
	if ($age == "")
	{
		$age = 0;
	}

	$dte = ($statflag ? $dtlc : $dtd);
	if ($dte < 0 && $dtd > 0)
	{
		print_r($dt);
		die("Sample $bc: invalid dtd/dte");
	}

	$gender = "U";
	if (isset($dt->cases[0]->demographic))
	{
		$gender = strtolower(trim($dt->cases[0]->demographic->gender));
	}
	else
	{
		print "WARNING: Sample $bc missing gender\n";
	}
	if ($gender == "female")
	{
		$gender = "F";
	}
	else if ($gender == "male")
	{
		$gender = "M";
	}
	else
	{
		$gender = "U";
	}

	$samp_json = json_encode($dt);

	$data[$sid] = array("stat" => $statflag, "dtd" => $dtd, "dtlc" => $dtlc, "json" => $samp_json,
							"dte" => $dte, "censor" => $censor, "age" => $age, "sex" => $gender, "alias" => $esi);
}
if ($numSamp != $numBC)
{
	print "sample mismatch: $numSamp != $numBC\n";	
}

$missing = 0;
foreach ($samp2ID as $bc => $sid)
{
	if (!isset($data[$sid]))
	{
		print "missing data for sample $bc (sid=$sid)\n";
		$missing++;
	}
}
if ($missing > 0)
{
	exit(0);
}

$st = dbps("insert into sampdt (sid,dtd,dtlc,dte,stat,censor,age,sex,fulldata) values(?,?,?,?,?,?,?,?,?)");
$st->bind_param("iiiiiiiss",$sid,$dtd,$dtlc,$dte,$stat,$censor,$age,$sex,$json);
$st2 = dbps("insert into sampalias (SID,lbl,idx) values(?,?,1)");
$st2->bind_param("is",$sid,$alias);
foreach ($samp2ID as $bc => $sid)
{
	$dt = $data[$sid];
	$stat 	= $dt["stat"];
	$dtd 	= $dt["dtd"];
	$dtlc 	= $dt["dtlc"];
	$censor = $dt["censor"];
	$dte 	= $dt["dte"];
	$age 	= $dt["age"];
	$sex 	= $dt["sex"];
	$json 	= $dt["json"];
	$alias 	= $dt["alias"];

	$st->execute();
	$st2->execute();
}
$st->close();
$st2->close();

?>

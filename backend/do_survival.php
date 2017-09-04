<?php
require_once("util.php");
require_once("db.php");

# 
# Do survival computation and load to DB
# Uses data in Rdata.tsv, presumed to have been already created
#

# directory and file names hard-coded in the R script
$outdir = "surv_tmp";  
$coxp_file = "coxfit.txt"; 		# cox fit pvalue
$survp_file = "survdiff.txt";	# survival differential pvalue
$strat_file = "strata.txt";		# risk stratum assignments
$surv_file = "survival.txt"; 			# y-axis of survival plot
$survtime_file = "survtimes.txt"; 		# y-axis of survival plot


$rdatafile = "lung/Rdata.tsv";
$crid = 4;
$dsid = 3;

$samp2ID = array();
$res = dbq("select id,lbl from samp where dsid=$dsid");
while ($r = $res->fetch_assoc())
{
	$sid = $r["id"];
	$lbl = $r["lbl"];
	$samp2ID[$lbl] = $sid;
}
$numSamp = count($samp2ID);

$grp2ID = array();
$res = dbq("select id,lbl from clst where crid=$crid and lvl=0");
while ($r = $res->fetch_assoc())
{
	$cid = $r["id"];
	$lbl = $r["lbl"];
	$grp2ID[$lbl] = $cid;
}
$numGrp = count($grp2ID);

# clear out old values
dbq("update clst set coxp=1, survp=1 where crid=$crid");
dbq("update lbls,clst set risk_strat=0 where lbls.cid=clst.id and clst.crid=$crid");
dbq("delete survdt.* from survdt,clst where survdt.cid=clst.id and clst.crid=$crid");

foreach ($grp2ID as $grp => $cid)
{
	system("rm -f $outdir/*");

	$cmd = "./group_survival.R $grp $rdatafile";
	$retval = "";
	print "$cmd\n";
	system($cmd,$retval);

	if ($retval != 0)
	{
		if ($retval != 75)
		{
			# some actual error happened
			die("group $grp ($cid) computation failed!\n");
		}
		# it just didn't reach coxph threshold
		dbq("update clst set coxp=1, survp=1 where id=$cid");
		continue;
	}

	# check all the files
	check_file("$outdir/$coxp_file");
	check_file("$outdir/$survp_file");
	check_file("$outdir/$surv_file");
	check_file("$outdir/$survtime_file");
	check_file("$outdir/$strat_file");

	# first load the p-values
	$coxp = trim(file_get_contents("$outdir/$coxp_file"));
	if (!is_numeric($coxp))
	{
		die("non-numeric coxp:$coxp\n");
	}
	$survp = trim(file_get_contents("$outdir/$survp_file"));
	if (!is_numeric($survp))
	{
		die("non-numeric survp:$survp\n");
	}
	dbq("update clst set coxp=$coxp, survp=$survp where id=$cid");

	# Load the risk strat assignments

	$fh = fopen("$outdir/$strat_file","r");
	$line = fgets($fh);	
	while (($line = fgets($fh)) != false)
	{
		$fields = preg_split('/\s+/',trim($line));
		if (count($fields) == 2)
		{
			$samp = $fields[0];
			$strat = $fields[1];
			if (!isset($samp2ID[$samp]))
			{
				die("unknown samp:$samp in strat file\n");
			}
			$sid = $samp2ID[$samp];
			dbq("update lbls set risk_strat=$strat where cid=$cid and sid=$sid");
		}
	}
	
	# Load the survival curve points
	# Note the strata are just appended together in the file
	# We increment stratum when the time point resets back to a lower value

	$timestr = trim(file_get_contents("$outdir/$survtime_file"));	
	$survstr = trim(file_get_contents("$outdir/$surv_file"));	
	
	$times = preg_split('/\s+/',$timestr);
	$survs = preg_split('/\s+/',$survstr);

	if (count($times) == 0)
	{
		die ("empty time data!\n");
	}
	if (count($times) != count($survs))
	{
		die ("Time data not matching surv data!\n");
	}
	$va = array();
	$prev_time = 1;
	$strat = 1;
	for ($i = 0; $i < count($times); $i++)
	{
		$cur_surv = $survs[$i];
		$cur_time = $times[$i];
		if ($cur_time < $prev_time)
		{
			$strat++;
		}
		$prev_time = $cur_time;
		$va[] = "($cid,$strat,$cur_surv,$cur_time)";
	}
	dbq("insert into survdt (cid,strat,surv,dte) values".implode(",",$va));
}

#################################################

function check_file($file)
{
	if (!is_file($file))
	{
		die("file missing: $file\n");
	}
}
?>










<?php
require_once("util.php");
require_once("db.php");

# 
# Do paired survival computation and load to DB
# Uses data in Rdata.tsv, presumed to have been already created
# Uses the top scoring groups of single-group survival, which
# must be done first
#
# Paired survival is almost the same as single, so the
# scripts have a lot of duplicated code. 

# directory and file names hard-coded in the R script
$outdir = "pair_surv_tmp";  
$coxp_file = "coxfit.txt"; 		# cox fit pvalue
$survp_file = "survdiff.txt";	# survival differential pvalue
$strat_file = "strata.txt";		# risk stratum assignments
$surv_file = "survival.txt"; 			# y-axis of survival plot
$survtime_file = "survtimes.txt"; 		# y-axis of survival plot

$pthresh = 0.1; # we'll look only at groups that scored at least this good alone


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
$res = dbq("select id,lbl from clst where crid=$crid and lvl=0 and survp <= $pthresh");
while ($r = $res->fetch_assoc())
{
	$cid = $r["id"];
	$lbl = $r["lbl"];
	$grp2ID[$lbl] = $cid;
}
$numGrp = count($grp2ID);

dbq("delete clst_pair.* from clst_pair, clst where clst_pair.CID1=clst.ID and clst.crid=$crid");
dbq("delete pair_survdt.* from pair_survdt, clst where pair_survdt.CID1=clst.ID and clst.crid=$crid");
dbq("delete pair_lbls.* from pair_lbls, clst where pair_lbls.CID1=clst.ID and clst.crid=$crid");

foreach ($grp2ID as $grp1 => $cid1)
{
	foreach ($grp2ID as $grp2 => $cid2)
	{
		if ($cid2 <= $cid1)
		{
			continue;
		}
		print "====================================================================\n";
		system("rm -f $outdir/*");
	
		dbq("insert into clst_pair (CID1,CID2) values($cid1,$cid2)");
		dbq("insert into pair_lbls (CID1,CID2,SID,risk_strat) ".
				" (select $cid1,$cid2,id,0 from samp where dsid=$dsid order by id asc)");

		$cmd = "./paired_survival.R $grp1 $grp2 $rdatafile";
		$retval = "";
		print "$cmd\n";
		system($cmd,$retval);

		if ($retval != 0)
		{
			if ($retval != 75)
			{
				# some actual error happened
				die("groups ($grp1,$grp2) CID=($cid1,$cid2) computation failed!\n");
			}
			# it just didn't reach coxph threshold
			dbq("update clst_pair set coxp=1, survp=1 where cid1=$cid1 and cid2=$cid2");
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
		dbq("update clst_pair set coxp=$coxp, survp=$survp where cid1=$cid1 and cid2=$cid2");

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
				dbq("update pair_lbls set risk_strat=$strat where cid1=$cid1 and cid2=$cid2 and sid=$sid");
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
			$va[] = "($cid1,$cid2,$strat,$cur_surv,$cur_time)";
		}
		dbq("insert into pair_survdt (cid1,cid2,strat,surv,dte) values".implode(",",$va));
	}
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










<?php
require_once("util.php");
$script_dir = $SCRIPTDIR;
$Rscript_dir = "$SCRIPTDIR/Rscripts";

$projname = $argv[1];
$rdatafile = $argv[2];

# 
# Pair threshold may need to be adjusted depending on the single factor
# scores
#
$pair_thresh = .1;   	# Factors with survival correlation meeting this threshold
						# will be candidats for the pair computation
$pair_topN = 10; 		# Use at most this many factors for the pair comp, starting with 
						# the most significant.

# directory and file names hard-coded in the R script
$outdir = "tmp/surv_tmp";  
$coxp_file = "coxfit.txt"; 		# cox fit pvalue
$survp_file = "survdiff.txt";	# survival differential pvalue
$strat_file = "strata.txt";		# risk stratum assignments
$surv_file = "survival.txt"; 			# y-axis of survival plot
$survtime_file = "survtimes.txt"; 		# y-axis of survival plot

if (!is_dir("./tmp"))
{
	mkdir("./tmp");
}
if (!is_dir($outdir))
{
	mkdir($outdir);
}


$crid = find_proj($projname);
$proj_info = array();
load_proj_data($proj_info,$crid);
$dsid = $proj_info["DSID"];

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

write_survival_table();

print("############### Begin single-factor survival ################\n");

dbq("update clst set coxp=1, survp=1 where crid=$crid");
dbq("update lbls,clst set risk_strat=0 where lbls.cid=clst.id and clst.crid=$crid");
dbq("delete survdt.* from survdt,clst where survdt.cid=clst.id and clst.crid=$crid");

foreach ($grp2ID as $grp => $cid)
{
	run_cmd("rm -f $outdir/*",$retval);

	$cmd = "$Rscript_dir/group_survival.R $grp $rdatafile";
	$retval = "";
	print "$cmd\n";
	run_cmd($cmd,$retval);

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
			if (!is_numeric($strat))
			{
				die("bad stratum $strat for $sid:$samp\n");
			}
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

print("############### Begin paired survival ################\n");

$grp2ID = array();
$res = dbq("select id,lbl from clst where crid=$crid and lvl=0 and survp <= $pair_thresh ".
				" order by survp asc limit $pair_topN ");
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
		run_cmd("rm -f $outdir/*",$retval);
	
		dbq("insert into clst_pair (CID1,CID2) values($cid1,$cid2)");
		dbq("insert into pair_lbls (CID1,CID2,SID,risk_strat) ".
				" (select $cid1,$cid2,id,0 from samp where dsid=$dsid order by id asc)");

		$cmd = "$Rscript_dir/paired_survival.R $grp1 $grp2 $rdatafile";
		$retval = "";
		print "$cmd\n";
		run_cmd($cmd,$retval);

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

#
# Overall survival
#

dbq("delete from survdt_ov where crid=$crid");

$cmd = "$Rscript_dir/overall_survival.R $rdatafile";
$retval = "";
print "$cmd\n";
run_cmd($cmd,$retval);

if ($retval != 0)
{
	die("group overall surv computation failed!\n");
}

check_file("$outdir/$surv_file");
check_file("$outdir/$survtime_file");

# Load the survival curve points

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
for ($i = 0; $i < count($times); $i++)
{
	$cur_surv = $survs[$i];
	$cur_time = $times[$i];
	if ($cur_time < $prev_time)
	{
		die("time switch!");
	}
	$prev_time = $cur_time;
	$va[] = "($crid,$cur_surv,$cur_time)";
}
dbq("insert into survdt_ov (crid,surv,dte) values".implode(",",$va));

###################################################

function build_survival_data_table()
{
	global $dsid,$crid,$rdatafile;

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
	file_put_contents($rdatafile,$outstr);
}
##################################################

function write_survival_table()
{
	global $rdatafile, $crid, $dsid, $numGrp;
	print "Writing survival data to $rdatafile\n";
	$conts = array();
	$sid2sname = array();
	$res = dbq("select samp.lbl as sname, samp.id as sid, clst.lbl as cnum, clst.id as cid, ".
				" lbls.clbl as clbl from lbls join clst on clst.id=lbls.cid ".
				" join samp on samp.id=lbls.sid where clst.crid=$crid ",1);
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
	file_put_contents($rdatafile,$outstr);

}
?>










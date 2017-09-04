<?php
require_once("db.php");
require_once("util.php");

$CRID = getval("crid",1);
$CID = getval("cid",0);
$CID_pair = getval("pair","");
$FT = getval("ft","");
$CID2 = 0;

check_numeric($CRID);
check_numeric($CID);

# Line graph: 3 variables == strata, samples = times of events
$graph_vars = "";
$graph_samps = "";
$graph_data = "";

$this_survp = 0;

print_r($_GET);

if ($CID_pair != "")
{
	$cids = explode("_",$CID_pair);
	$CID = $cids[0];
	$CID2 = $cids[1];
	check_numeric($CID2);

	$res = dbq("select survp from clst_pair where cid1=$CID and cid2=$CID2 ");
	$r = $res->fetch_assoc();
	$this_survp = $r["survp"];

	get_pair_surv_data($graph_vars,$graph_samps,$graph_data);
}
else if ($CID != 0)
{
	$res = dbq("select survp from clst where clst.id=$CID ");
	$r = $res->fetch_assoc();
	$this_survp 	= $r["survp"];

	get_surv_data($graph_vars,$graph_samps,$graph_data);
}
?>

<head>
<link rel="stylesheet" href="http://www.canvasxpress.org/css/canvasXpress.css" type="text/css"/>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
</head>
 <body >

<form method="get">
<input type="hidden" name="ft" value="<?php echo $FT ?>">
<input type="hidden" name="crid" value="<?php print $CRID ?>">
<table cellpadding=5>
	<tr>
		<td><b>Survival:</b></td>
		<td>Cluster: <?php print clst_sel_surv("cid",$CID,$CID2) ?> </td>
		<td>Paired: <?php print clst_sel_pair("pair",$CID,$CID2) ?> </td>
		<td align="right" style="font-size:1.4em; padding-left:50px;color:#333333" >
			<span id="popout_btn" title="Open in a new page" style="cursor:pointer">&nbsp;&#9654;&nbsp;</span>
		</td>
	</tr>
</table>
</form>
<script>
$('#sel_cid').change(function() 
{
	$('#sel_pair').val("");
	$(this).closest('form').submit();	
});
$('#sel_pair').change(function() 
{
	$('#sel_cid').val("0");
	$(this).closest('form').submit();	
});
$('#param_btn').click(function()
{
	$('#params').toggle();
});
$('#popout_btn').click(function()
{
 	var win = window.open(window.location.href, '_blank');
  	win.focus();
});
</script>

<?php if ($CID == 0) { exit(0); } ?>

Survival differential p-value (risk stratum R3 vs. R1): <?php echo $this_survp ?>

<script type="text/javascript" src="http://www.canvasxpress.org/js/canvasXpress.min.js"></script>
    <canvas  id="canvasId" width="800" height="540"></canvas>
<script>
var data = {"y": {"vars": <?php echo $graph_vars ?>,
				  "smps":<?php echo $graph_samps ?>,
				  "data": <?php echo $graph_data ?>
				 }
			};
var conf = {"graphType": "Line",
			"lineDecoration" : false,
			"smpLabelInterval" : 300,
			"smpTitle" : "Months",
			"graphOrientation" : "vertical"
			};                 
var cX = new CanvasXpress("canvasId", data, conf);
</script>

</body>

<?php

##################################################################

function get_surv_data(&$varstr,&$sampstr,&$datastr)
{
	global $CID;

	$res = dbq("select max(strat) as maxstrat from survdt where cid=$CID ");
	$r = $res->fetch_assoc();
	$numstrat = $r["maxstrat"];	

	$vars = array();   # variables = strata
	$data = array();   # survival per stratum for each timepoint
	for ($s = 1; $s <= $numstrat; $s++)
	{
		$vars[] = "\"R$s\"";
		$data[$s-1] = array();
		$data[$s-1][0] = 1.0;
	}

	$res = dbq("select dte,strat,surv from survdt where cid=$CID   order by dte asc");
	$prev_time = 0;
	$times = array();
	$times[0] = 1;
	while ($r = $res->fetch_assoc())
	{
		$time 	= $r["dte"];
		$strat 	= $r["strat"];
		$surv 	= $r["surv"];
		
		#
		# Here we ensure that each time point is represented across all
		# of the strata, by initializing the strata from prior timepoints.
		# Later we will fill in additional time points for best graph display. 
		#
		if (!isset($times[$time]))
		{
			for ($s = 1; $s <= $numstrat; $s++)
			{
				$data[$s-1][$time] = $data[$s-1][$prev_time];
			}
		}
		$times[$time] = 1;
		$data[$strat-1][$time] = $surv;
		$prev_time = $time;
	}
	$max_time = $prev_time;
	
	# fill in missing times
	for ($t = 0; $t <= $max_time; $t++)
	{
		if (!isset($times[$t]))
		{
			$times[$t] = 1;
			for ($s = 1; $s <= $numstrat; $s++)
			{
				$data[$s-1][$t] = $data[$s-1][$t-1];
			}
		}
	}
	
	$samps = array();	
	for ($t = 0; $t <= $max_time; $t++)
	{
		if (isset($times[$t]))
		{
			$months = $t/30.0;
			$samps[] = "\"$months\"";
		}
	}
	$varstr = "[".implode(",\n",$vars)."]";
	$sampstr = "[".implode(",\n",$samps)."]";

	$datastrs = array();
	foreach ($data as $strat => $vals)
	{
		ksort($vals, SORT_NUMERIC);
		$datastrs[] = "[".implode(",\n",$vals)."]";
	}
	$datastr = "[".implode(",\n",$datastrs)."]";
}

######################################################################

function get_pair_surv_data(&$varstr,&$sampstr,&$datastr)
{
	global $CID;
	global $CID2;

	$res = dbq("select max(strat) as maxstrat from pair_survdt where cid1=$CID and cid2=$CID2 ");
	$r = $res->fetch_assoc();
	$numstrat = $r["maxstrat"];	

	$vars = array();   # variables = strata
	$data = array();   # survival per stratum for each timepoint
	for ($s = 1; $s <= $numstrat; $s++)
	{
		$vars[] = "\"R$s\"";
		$data[$s-1] = array();
		$data[$s-1][0] = 1.0;
	}

	$res = dbq("select dte,strat,surv from pair_survdt where cid1=$CID and cid2=$CID2  order by dte asc");
	$prev_time = 0;
	$times = array();
	$times[0] = 1;
	while ($r = $res->fetch_assoc())
	{
		$time 	= $r["dte"];
		$strat 	= $r["strat"];
		$surv 	= $r["surv"];
		
		#
		# Here we ensure that each time point is represented across all
		# of the strata, by initializing the strata from prior timepoints.
		# Later we will fill in additional time points for best graph display. 
		#
		if (!isset($times[$time]))
		{
			for ($s = 1; $s <= $numstrat; $s++)
			{
				$data[$s-1][$time] = $data[$s-1][$prev_time];
			}
		}
		$times[$time] = 1;
		$data[$strat-1][$time] = $surv;
		$prev_time = $time;
	}
	$max_time = $prev_time;
	
	# fill in missing times
	for ($t = 0; $t <= $max_time; $t++)
	{
		if (!isset($times[$t]))
		{
			$times[$t] = 1;
			for ($s = 1; $s <= $numstrat; $s++)
			{
				$data[$s-1][$t] = $data[$s-1][$t-1];
			}
		}
	}
	
	$samps = array();	
	for ($t = 0; $t <= $max_time; $t++)
	{
		if (isset($times[$t]))
		{
			$months = $t/30.0;
			$samps[] = "\"$months\"";
		}
	}
	$varstr = "[".implode(",\n",$vars)."]";
	$sampstr = "[".implode(",\n",$samps)."]";

	$datastrs = array();
	foreach ($data as $strat => $vals)
	{
		ksort($vals, SORT_NUMERIC);
		$datastrs[] = "[".implode(",\n",$vals)."]";
	}
	$datastr = "[".implode(",\n",$datastrs)."]";
}

################################################################

# slightly awkward here as we need to ensure that nothing is
# selected here if a pair was specified (CID2 > 0)
function clst_sel_surv($name,$CID,$CID2)
{
	global $CRID;
	$selected = ($CID == 0 || $CID2 != 0 ? " selected " : "");
	$opts[] = "<option value='0' $selected>--choose--</option>";

	$res = dbq("select ID, lbl, survp from clst ".
		" where clst.CRID=$CRID and clst.lvl=0 and clst.survp < 1 ".
		" order by clst.survp asc ");
	while ($r = $res->fetch_assoc())
	{
		$ID = $r["ID"];
		$lbl = $r["lbl"];
		$pval = $r["survp"];
		$selected = ($ID == $CID && $CID2 == 0 ? " selected " : "");
		#$pval = floor(1000*$pval)/1000;
		$opts[] = "<option value=$ID $selected>$lbl (p=$pval)</option>";
	}
	return "<select name='$name' id='sel_$name'>\n".implode("\n",$opts)."\s</select>\n";
}

################################################################

function clst_sel_pair($name,$CID,$CID2)
{
	global $CRID;
	$selected = ($CID2 == 0 ? " selected " : "");
	$opts[] = "<option value='' $selected>--choose--</option>";

	$res = dbq("select pc.cid1,pc.cid2, clst1.lbl as lbl1,clst2.lbl as lbl2, pc.survp ".
		" from clst_pair as pc ".
		" join clst as clst1 on clst1.id=pc.cid1 ".	
		" join clst as clst2 on clst2.id=pc.cid2 ".	
		" where pc.survp < 1 ".
		" order by pc.survp asc ");
	while ($r = $res->fetch_assoc())
	{
		$cid1 = $r["cid1"];
		$cid2 = $r["cid2"];
		$lbl1 = $r["lbl1"];
		$lbl2 = $r["lbl2"];
		$pval = $r["survp"];
		$selected = ($cid1==$CID && $cid2==$CID2 ? " selected " : "");
		$opt_lbl = $lbl1."_".$lbl2;
		$opt_val = $cid1."_".$cid2;
		$opts[] = "<option value='$opt_lbl' $selected>$opt_lbl (p=$pval)</option>";
	}
	return "<select name='$name' id='sel_$name'>\n".implode("\n",$opts)."\s</select>\n";
}

?>

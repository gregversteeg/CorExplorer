<?php
require_once("util.php");

$CRID = getint("crid",1);
$CID = getint("cid",0);
$CID_pair = getval("pair","");
$FT = getval("ft","");
$CID2 = 0;
$FromForm = getint("fromform",0); # tell us if it's initial page load or form submit
$Pvalsort = checkbox_val("pvalsort",1,$FromForm);

if (!read_access($CRID))
{
	die("access denied");
}

check_numeric($CRID);
check_numeric($CID);

# Line graph: 3 variables == strata, samples = times of events
$graph_vars = "";
$graph_samps = "";
$graph_data = "";

$this_survp = 0;

if ($CID_pair != "")
{
	$cids = explode("_",$CID_pair);
	$CID = $cids[0];
	$CID2 = $cids[1];
	check_numeric($CID2);

	$st = $DB->prepare("select survp from clst_pair where cid1=? and cid2=? ");
	$st->bind_param("ii",$CID,$CID2);
	$st->bind_result($this_survp);
	$st->execute();
	$st->fetch();
	$st->close();

	get_pair_surv_data($graph_vars,$graph_samps,$graph_data);
}
else if ($CID != 0)
{
	$st = $DB->prepare("select survp from clst where id=? ");
	$st->bind_param("i",$CID);
	$st->bind_result($this_survp);
	$st->execute();
	$st->fetch();
	$st->close();

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
<input type="hidden" name="fromform" value="1">
<table cellpadding=5>
	<tr>
		<td><b>Survival:</b></td>
		<td>Single Factor: <?php print clst_sel_surv("cid",$CID,$CID2) ?> </td>
		<td>
		Sort by p-val:	<input name="pvalsort" id="chk_pvalsort" type="checkbox" <?php checked($Pvalsort,1) ?>>
		</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td>Paired Factors: <?php print clst_sel_pair("pair",$CID,$CID2) ?> </td>
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
$('#chk_pvalsort').change(function() 
{
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
			"smpLabelRotate" : 90,
			"graphOrientation" : "vertical"
			};                 
var cX = new CanvasXpress("canvasId", data, conf);
</script>

</body>

<?php

##################################################################

function get_surv_data(&$varstr,&$sampstr,&$datastr)
{
	global $CID,$DB;

	$st = $DB->prepare("select max(strat) as maxstrat from survdt where cid=? ");
	$st->bind_param("i",$CID);
	$st->bind_result($numstrat);
	$st->execute();
	$st->fetch();
	$st->close();

	$vars = array();   # variables = strata
	$data = array();   # survival per stratum for each timepoint
	for ($s = 1; $s <= $numstrat; $s++)
	{
		$vars[] = "\"R$s\"";
		$data[$s-1] = array();
		$data[$s-1][0] = 1.0;
	}

	$st = $DB->prepare("select dte,strat,surv from survdt where cid=? order by dte asc");
	$st->bind_param("i",$CID);
	$st->bind_result($time,$strat,$surv);
	$st->execute();
	$prev_time = 0;
	$times = array();
	$times[0] = 1;
	while ($st->fetch())
	{
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
	$st->close();
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
	
	# The "samples" in the language of CanvasXpress, are the time points.
	# The "variables" are the strata. 
	# The "data" are concatentated arrays, one for each stratum, that give the
	# survival values at each time point. 
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
	global $CID, $CID2, $DB;

	$st = $DB->prepare("select max(strat) as maxstrat from pair_survdt where cid1=? and cid2=? ");
	$st->bind_param("ii",$CID,$CID2);
	$st->bind_result($numstrat);
	$st->execute();
	$st->fetch();
	$st->close();

	$vars = array();   # variables = strata
	$data = array();   # survival per stratum for each timepoint
	for ($s = 1; $s <= $numstrat; $s++)
	{
		$vars[] = "\"R$s\"";
		$data[$s-1] = array();
		$data[$s-1][0] = 1.0;
	}

	$st = $DB->prepare("select dte,strat,surv from pair_survdt where cid1=? and cid2=? order by dte asc");
	$st->bind_param("ii",$CID,$CID2);
	$st->bind_result($time,$strat,$surv);
	$st->execute();
	$prev_time = 0;
	$times = array();
	$times[0] = 1;
	while ($st->fetch())
	{
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
	$st->close();
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
	global $CRID, $DB, $Pvalsort;
	$selected = ($CID == 0 || $CID2 != 0 ? " selected " : "");
	$opts[] = "<option value='0' $selected>--choose--</option>";

	$sortby = ($Pvalsort ? " order by clst.survp asc " : " order by clst.ID asc ");

	$st = $DB->prepare("select ID, lbl, survp from clst ".
		" where clst.CRID=? and clst.lvl=0 and clst.survp < 1 ".
		" $sortby ");
	$st->bind_param("i",$CRID);
	$st->bind_result($ID,$lbl,$pval);
	$st->execute();
	while ($st->fetch())
	{
		$selected = ($ID == $CID && $CID2 == 0 ? " selected " : "");
		#$pval = floor(1000*$pval)/1000;
		$opts[] = "<option value=$ID $selected>$lbl (p=$pval)</option>";
	}
	$st->close();
	return "<select name='$name' id='sel_$name'>\n".implode("\n",$opts)."\s</select>\n";
}

################################################################

function clst_sel_pair($name,$CID,$CID2)
{
	global $CRID, $DB;
	$selected = ($CID2 == 0 ? " selected " : "");
	$opts[] = "<option value='' $selected>--choose--</option>";

	$st = $DB->prepare("select pc.cid1,pc.cid2, clst1.lbl as lbl1,clst2.lbl as lbl2, pc.survp ".
		" from clst_pair as pc ".
		" join clst as clst1 on clst1.id=pc.cid1 ".	
		" join clst as clst2 on clst2.id=pc.cid2 ".	
		" where pc.survp < 1 and clst1.crid=? and clst2.crid=? ".
		" order by pc.survp asc ");
	$st->bind_param("ii",$CRID,$CRID);
	$st->bind_result($cid1,$cid2,$lbl1,$lbl2,$pval);
	$st->execute();
	while ($st->fetch())
	{
		$selected = ($cid1==$CID && $cid2==$CID2 ? " selected " : "");
		$opt_lbl = $lbl1."_".$lbl2;
		$opt_val = $cid1."_".$cid2;
		$opts[] = "<option value='$opt_val' $selected>$opt_lbl (p=$pval)</option>";
	}
	$st->close();
	return "<select name='$name' id='sel_$name'>\n".implode("\n",$opts)."\s</select>\n";
}

?>

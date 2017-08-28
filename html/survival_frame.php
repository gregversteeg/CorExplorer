<?php
require_once("db.php");
require_once("util.php");

$CRID = getval("crid",1);
$CID_sel = getval("cid",0);
$FT = getval("ft","");

# Line graph: 3 variables == strata, samples = times of events
$graph_vars = "";
$graph_samps = "";
$graph_data = "";
get_surv_data($graph_vars,$graph_samps,$graph_data);
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
		<td>Cluster: <?php print clst_sel_surv("cid",$CID_sel) ?> </td>
		<td align="right" style="font-size:1.4em; padding-left:50px;color:#333333" >
			<span id="popout_btn" title="Open in a new page" style="cursor:pointer">&nbsp;&#9654;&nbsp;</span>
		</td>
	</tr>
</table>
</form>
<script>
$('#sel_cid').change(function() 
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
<?php if ($CID_sel == 0) { exit(0); } ?>
<?php
	$res = dbq("select survp,lbl,count(*) as ngenes from clst ".
				" join g2c on g2c.cid=clst.id where clst.id=$CID_sel ".
				" group by clst.id ");
	$r = $res->fetch_assoc();
	$survp 	= $r["survp"];
	$lbl 	= $r["lbl"];
	$ngenes = $r["ngenes"];
?>

Survival differential p-value (risk stratum R3 vs. R1): <?php echo $survp ?>

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

function get_surv_data(&$varstr,&$sampstr,&$datastr)
{
	global $CID_sel;

	$res = dbq("select max(strat) as maxstrat from survdt where cid=$CID_sel ");
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

	$res = dbq("select dte,strat,surv from survdt where cid=$CID_sel   order by dte asc");
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

function clst_sel_surv($name,$CID)
{
	global $CRID;
	$selected = ($CID == 0 ? " selected " : "");
	$opts[] = "<option value='0' $selected>--choose--</option>";

	$res = dbq("select ID, lbl, lvl, survp, count(*) as size from clst ".
		" join g2c on g2c.CID=clst.ID ".
		" where clst.CRID=$CRID and clst.lvl=0 and clst.survp < 1 ".
		" group by clst.ID order by survp asc",1);
	while ($r = $res->fetch_assoc())
	{
		$ID = $r["ID"];
		$lbl = $r["lbl"];
		$pval = $r["survp"];
		$lvl = $r["lvl"] + 1;
		$size = $r["size"] + 1;
		$selected = ($ID == $CID ? " selected " : "");
		#$pval = floor(1000*$pval)/1000;
		$opts[] = "<option value=$ID $selected>Layer$lvl : $lbl (p=$pval)</option>";
	}
	# due to the g2c join, the previous only got layer 1
	$res = dbq("select ID, lbl, lvl  from clst ".
		" where clst.CRID=$CRID and lvl > 0 $lvlwhere ".
		" group by clst.ID ");
	while ($r = $res->fetch_assoc())
	{
		$ID = $r["ID"];
		$lbl = $r["lbl"];
		$lvl = $r["lvl"] + 1;
		$selected = ($ID == $CID ? " selected " : "");
		$opts[] = "<option value=$ID $selected>Layer$lvl : $lbl </option>";
	}
	return "<select name='$name' id='sel_$name'>\n".implode("\n",$opts)."\s</select>\n";
}

?>

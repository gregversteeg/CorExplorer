<?php
require_once("db.php");
require_once("util.php");

$minWt = getval("mw",0);
$CRID = getval("crid",0);
$CID_sel = getval("cid",0);
$numGenes = getval("ng",20);
$maxZ = getval("maxz",2);
$FT = getval("ft","");

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
		<td><b>Heatmap:</b></td>
		<td> Cluster: <?php print clst_sel("cid",$CID_sel,0,"--choose--") ?> </td>
		<td align="right" style="font-size:1.4em; padding-left:50px;color:#333333" >
			<span id="param_btn" title="Edit parameters" style="cursor:pointer">&nbsp;&#x270e;&nbsp;</span>
			<span id="popout_btn" title="Open in a new page" style="cursor:pointer">&nbsp;&#9654;&nbsp;</span>
		</td>
	</tr>
</table>
<table id="params" style="display:none">
	<tr>
		<td>Min weight:
			 <input name="mw" type="text" size="4" value="<?php print $minWt ?>">
		</td>
		<td width=10>&nbsp;</td>
		<td>Num genes: <input name="ng" type="text" size="4" value="<?php print $numGenes ?>"> 
		</td>
		<td width=10>&nbsp;</td>
		<td>Max Z: <input name="maxz" type="text" size="2" value="<?php print $maxZ ?>"> 
		</td>
		<td width=10>&nbsp;</td>
		<td><input type="submit" value="Apply"></td>
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

<?php
if ($CID_sel != 0) 
{  

	$heat_genes = "";
	$heat_samps = "";
	$heat_expr = "";
	$heat_wts = "";
	get_heat_data($heat_genes,$heat_samps,$heat_expr,$heat_wts,
				$CRID,$CID_sel,$minWt,$numGenes,$maxZ);
	echo <<<END
<script type="text/javascript" src="http://www.canvasxpress.org/js/canvasXpress.min.js"></script>
<canvas  id="canvasId" width="540" height="540"></canvas>

<script>
var data = {"y": {"vars": $heat_genes,
				  "smps": $heat_samps,
				  "data": $heat_expr
				 },
			"z": {"weight": $heat_wts
				}
			};
var conf = {"graphType": "Heatmap",
			"heatmapCellBox" : false,
			"showSampleNames" : false,
			"isReproducibleResearch" : false,
			"colorSpectrum": ["#00ff00", "#000000", "#ff0000"]};                 
var cX = new CanvasXpress("canvasId", data, conf);
</script>
END;
}

?>
</body>

<?php

function get_heat_data(&$heat_genes,&$heat_samps,&$heat_expr,&$heat_wts,
				$crid, $cid,$minwt,$maxGenes,$maxZ)
{
	$res = dbq("select dsid,glid from clr where id=$crid");
	$r = $res->fetch_assoc();
	$dsid = $r["dsid"];
	$glid = $r["glid"];

	$genes = array();
	$samps = array();
	$data = array();
	$genestrs = array();
	$sampstrs = array();
	$wtstrs = array();

	$maxsamps = 40000;

	# first get the samples sorted by continuous label
	$res = dbq("select sid,samp.lbl from lbls join samp on samp.id=lbls.sid ".
				" where cid=$cid order by clbl asc, sid asc limit $maxsamps");
	while ($r = $res->fetch_assoc())
	{
		$sid = $r["sid"];
		$sname = $r["lbl"];
		$samps[] = $sid;
		$sampstrs[] = "\"$sname\"";
	}
	$numSamps = count($samps);
	
	# get the genes in the cluster
	$res = dbq("select gid,lbl,wt from g2c join glist on glist.id=g2c.gid ".
				" where g2c.cid=$cid and g2c.wt >= $minwt ".
				" order by g2c.mi desc limit $maxGenes ");
	while ($r = $res->fetch_assoc())
	{
		$gid = $r["gid"];
		$wt = $r["wt"];
		$gname = $r["lbl"];
		$genes[] = $gid;
		$genestrs[] = "\"$gname\"";
		$wt = .01*floor(100*$wt);
		$wtstrs[] = "\"$wt\"";
	}
	$numGenes = count($genes);
	
	$vals = array();
	for ($g = 0; $g < $numGenes; $g++)
	{
		$gid = $genes[$g];
		$snum = 0;
		$res = dbq("select expr.sid,expr.logz from expr  ".
						" join lbls on lbls.sid=expr.sid ".
						" where expr.dsid=$dsid and expr.gid=$gid and lbls.cid=$cid ".
						" order by lbls.clbl asc, lbls.sid asc  limit $maxsamps"); 
		$zs = array();
		while ($r = $res->fetch_assoc())
		{
			$sid = $r["sid"];
			$z = $r["logz"];
			if ($z > $maxZ)
			{
				$z = $maxZ;
			}
			if ($z < -$maxZ)
			{
				$z = -$maxZ;
			}
			$zs[] = $z;
			if ($sid != $samps[$snum])
			{
				# sanity check 
				die("mismatch in sample order at gid=$gid, snum=$snum, sid=$sid, should be".
						$samps[$snum]."!");
			}
			$snum++;
		}
		$vals[] = "[".implode(",",$zs)."]";
	}

	$heat_genes = "[".implode(",",$genestrs)."]";
	$heat_samps = "[".implode(",",$sampstrs)."]";
	$heat_expr = "[".implode(",",$vals)."]";
	$heat_wts = "[".implode(",",$wtstrs)."]";
}


?>

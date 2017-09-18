<?php
require_once("db.php");
require_once("util.php");

$minWt = getnum("mw",0);
$CRID = getint("crid",0);
$CID_sel = getint("cid",0);
$numGenes = getint("ng",100);
$maxZ = getval("maxz",2);
$Use_hugo = checkbox_val("use_hugo",1,1);

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
		<td> Factor: <?php print clst_sel("cid",$CID_sel,0,"--choose--") ?> </td>
		<td align="right" style="font-size:1.4em; padding-left:50px;color:#333333" >
			<span id="param_btn" title="Edit parameters" style="cursor:pointer">&nbsp;&#x270e;&nbsp;</span>
			<span id="popout_btn" title="Open in a new page" style="cursor:pointer">&nbsp;&#9654;&nbsp;</span>
		</td>
	</tr>
</table>
<table id="params" >
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
		<td title="<?php print tip_text('hugo_names') ?>">HUGO names:
			 <input name="use_hugo" id="use_hugo_chk" type="checkbox" <?php checked($Use_hugo,0) ?>>
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
	global $DB, $Use_hugo;
	$st = $DB->prepare("select dsid,glid from clr where id=?");
	$st->bind_param("i",$crid);
	$st->bind_result($dsid,$glid);
	$st->execute();
	$st->fetch();
	$st->close();

	$genes = array();
	$samps = array();
	$data = array();
	$genestrs = array();
	$sampstrs = array();
	$wtstrs = array();

	$maxsamps = 40000;

	# first get the samples sorted by continuous label
	$st = $DB->prepare("select sid,samp.lbl from lbls join samp on samp.id=lbls.sid ".
				" where cid=? order by clbl asc, sid asc limit $maxsamps");
	$st->bind_param("i",$cid);
	$st->bind_result($sid,$sname);
	$st->execute();
	while ($st->fetch())
	{
		$samps[] = $sid;
		$sampstrs[] = "\"$sname\"";
	}
	$st->close();
	$numSamps = count($samps);
	
	# get the genes in the cluster
	$st = $DB->prepare("select gid,lbl,hugo,wt from g2c join glist on glist.id=g2c.gid ".
				" where g2c.cid=? and g2c.wt >= ? ".
				" order by g2c.mi desc limit $maxGenes ");
	$st->bind_param("id",$cid,$minwt);
	$st->bind_result($gid,$gname,$hugo,$wt);
	$st->execute();
	while ($st->fetch())
	{
		$genes[] = $gid;
		$genestrs[] = ($Use_hugo ? "\"$hugo\"" : "\"$gname\"");
		$wt = .01*floor(100*$wt);
		$wtstrs[] = "\"$wt\"";
	}
	$st->close();
	$numGenes = count($genes);
	
	$vals = array();
	for ($g = 0; $g < $numGenes; $g++)
	{
		$gid = $genes[$g];
		$snum = 0;
		$st = $DB->prepare("select expr.sid,expr.logz from expr  ".
						" join lbls on lbls.sid=expr.sid ".
						" where expr.dsid=? and expr.gid=? and lbls.cid=? ".
						" order by lbls.clbl asc, lbls.sid asc  limit $maxsamps"); 
		$zs = array();
		$st->bind_param("iii",$dsid,$gid,$cid);
		$st->bind_result($sid,$z);
		$st->execute();
		while ($st->fetch())
		{
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
		$st->close();
		$vals[] = "[".implode(",",$zs)."]";
	}

	$heat_genes = "[".implode(",",$genestrs)."]";
	$heat_samps = "[".implode(",",$sampstrs)."]";
	$heat_expr = "[".implode(",",$vals)."]";
	$heat_wts = "[".implode(",",$wtstrs)."]";
}


?>

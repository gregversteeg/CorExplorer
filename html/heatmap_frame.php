<?php
require_once("util.php");

$minWt = getnum("mw",0);
$CRID = getint("crid",0);
$CID_sel = getint("cid",0);
$numGenes = getint("ng",100);
$numSamps = getint("ns",500);
$maxZ = getval("maxz",2);
$Use_hugo = checkbox_val("use_hugo",1,1);

if (!read_access($CRID))
{
	die("access denied");
}
?>

<head>
<style>
.axisLabel
{
	font-family:monospace;
	font-size:3px;
}
</style>
<script src="https://d3js.org/d3.v5.min.js"></script>
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
		<!--td>Num samples: <input name="ns" type="text" size="4" value="<?php print $numSamps ?>"> 
		</td-->
		<td width=10>&nbsp;</td>
		<td>Max Z: <input name="maxz" type="text" size="2" value="<?php print $maxZ ?>"> 
		</td>
		<td title="<?php print tip_text('hugo_names') ?>">HUGO names:
			 <input name="use_hugo" id="use_hugo_chk" type="checkbox" <?php checked($Use_hugo,1) ?>>
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

<div id="heatmap" ></div>
<!--table width=500 height=500>
	<tr>
		<td id="heatmap" width="100%" height="100%">
		</td>
	</tr>
</table-->

<?php
if ($CID_sel != 0) 
{  

	$heat_genes = "";
	$heat_samps = "";
	$heat_expr = "";
	$heat_wts = "";
	$samp_strata = "";
	get_heat_data($heat_genes,$heat_samps,$heat_expr,$heat_wts,$samp_strata,
				$CRID,$CID_sel,$minWt,$numGenes,$numSamps,$maxZ);
	echo <<<END



<script>
var genes = $heat_genes ;
var samps = $heat_samps ;
var vals = $heat_expr ;

//var margin = { top: 100, right: 10, bottom: 50, left: 300 },
var margin = { top: 50, right: 0, bottom: 0, left: 60 },
cellSize=3;
numCols = genes.length;
numRows = samps.length;
width = numCols*cellSize + margin.left + margin.right;
height = numRows*cellSize + margin.top + margin.bottom;
var maxZ = $maxZ;
var cscalePos = d3.scaleLinear() .range(["black", "red"]) .domain([0,maxZ])
var cscaleNeg = d3.scaleLinear() .range(["green", "black"]) .domain([-maxZ,0])

var svg = d3.select("#heatmap")
			.append("div")
			.classed("svg-container",true)
            .append("svg")
   .attr("viewBox", "0 0 " + width + " " +  height)
	.append("g")
    .attr("transform",
          "translate(" + margin.left + "," + margin.top + ")");

/*var svg = d3.select("#heatmap")
			.append("div")
			.classed("svg-container",true)
            .append("svg")
.attr("preserveAspectRatio", "xMinYMin meet")
   .attr("viewBox", "0 0 " + width + " " +  height)
	.classed("svg-content-responsive",true)
	.append("g")
    .attr("transform",
          "translate(" + margin.left + "," + margin.top + ")");
*/

var rowLabels = svg.append("g")
      .selectAll(".rowLabelg")
      .data(samps)
      .enter()
      .append("text")
      .text(function (d) { return d.lbl; })
      .attr("x", 0)
      .attr("y", function (d, i) { return d.i* cellSize; })
      .style("text-anchor", "end")
      .attr("transform", "translate(-2,0)") 
      //.attr("class", function (d,i) { return "rowLabel mono r"+i;} ) 
	  .attr("class","axisLabel")
      ;

var colLabels = svg.append("g")
      .selectAll(".colLabelg")
      .data(genes)
      .enter()
      .append("text")
      .text(function (d) { return d.lbl; })
      .attr("x", 0)
      .attr("y", function (d, i) {return d.i * cellSize; })
      .style("text-anchor", "left")
      .attr("transform", "translate(2,-2) rotate (-90)")
      .attr("class", "axisLabel" )
      ;
var heatMap = svg.append("g").attr("class","g3")
        .selectAll(".cellg")
        .data(vals,function(d){return d.s+":"+d.g;})
        .enter()
        .append("rect")
        .attr("x", function(d) { return d.g * cellSize; })
        .attr("y", function(d) { return d.s * cellSize; })
        .attr("width", cellSize)
        .attr("height", cellSize)
        .style("fill", function(d) { return (d.z >= 0 ? cscalePos(d.z) : cscaleNeg(d.z)); });

var zoom = d3.zoom()
    .scaleExtent([1, 40])
    //.translateExtent([[-100, -100], [width + 90, height + 100]])
    .on("zoom", zoomed);

svg.call(zoom);

function zoomed() {
  	heatMap.attr("transform", d3.event.transform);
  	rowLabels.attr("transform", d3.event.transform.toString() + " translate(-2,0) ");
  	colLabels.attr("transform", d3.event.transform.toString() + " translate(2,-2) rotate(-90) ");
}

</script>
END;
}
?>
</body>

<?php

function get_heat_data(&$heat_genes,&$heat_samps,&$heat_expr,&$heat_wts,&$samp_strata,
				$crid, $cid,$minwt,$maxGenes,$maxsamps,$maxZ)
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
	$rstrat = array();

	# first get the samples sorted by continuous label
	# also get the risk strata
	$st = $DB->prepare("select sid,samp.lbl,risk_strat from lbls join samp on samp.id=lbls.sid ".
				" where cid=? order by clbl asc, sid asc ");
	$st->bind_param("i",$cid);
	$st->bind_result($sid,$sname,$risk_strat);
	$st->execute();
	while ($st->fetch())
	{
		$samps[] = $sid;
		$sampstrs[] = $sname;
		$rstrat[] = "\"$risk_strat\"";
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
	$genenum = 0;
	while ($st->fetch())
	{
		$genenum++;
		$genes[] = $gid;
		$genename = ($Use_hugo ? $hugo : $gname);
		$genestrs[] = "{lbl:\"$genename\",i:$genenum}";
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
						" order by lbls.clbl asc, lbls.sid asc  "); 
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
		$vals[] = $zs;
	}
	# Now we subset the samples from the top and bottom
	if (0) #$maxsamps < $numSamps)
	{
		$samps2 = array();
		for ($i = 0; $i < $maxsamps/2; $i++)
		{
			$samps2[] = $sampstrs[$i];	
		}
		for ($i = $maxsamps/2; $i >= 1; $i--)
		{
			$samps2[] = $sampstrs[$numSamps - $i];	
		}
		$vals2 = array();
		for ($g = 0; $g < $numGenes; $g++)
		{
			$newvals = array();
			for ($i = 0; $i < $maxsamps/2; $i++)
			{
				$newvals[] = $vals[$g][$i];	
			}
			for ($i = $maxsamps/2; $i >= 1; $i--)
			{
				$newvals[] = $vals[$g][$numSamps - $i];	
			}
			$vals2[] = $newvals;
		}
		$numSamps = $maxsamps;
		$sampstrs = $samps2;
		$vals = $vals2;
	} 
	# put the sample names into array with index numbers, as we need for the page
	$sampobjs = array();
	for ($i = 1; $i <= count($sampstrs); $i++)
	{
		$samp = $sampstrs[$i-1];
		$sampobjs[] = "{lbl:\"$samp\",i:$i}";
	}
	
	$heatstrs = array();
	for ($g = 0; $g < $numGenes; $g++)
	{
		for ($s = 0; $s < $numSamps; $s++)
		{
			$z = $vals[$g][$s];
			$valstr = "{g:$g, s:$s, z:$z}";
			$heatstrs[] = $valstr;
		}
	}
	$heat_expr = "[".implode(",",$heatstrs)."]";
	$heat_genes = "[".implode(",",$genestrs)."]";
	$heat_samps = "[".implode(",",$sampobjs)."]";
	#$samp_strata = "[".implode(",",$rstrat)."]";
	#$heat_wts = "[".implode(",",$wtstrs)."]";
	
}


?>

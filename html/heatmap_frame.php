<?php
require_once("util.php");

$minWt = getnum("mw",0.05);
$CRID = getint("crid",0);
$CID_sel = getint("cid",0);
$numGenes = getint("ng",100);
$numSamps = getint("ns",5000);
$maxZ = getval("maxz",2);
$FromForm = getint("fromform",0); # tell us if it's initial page load or form submit
$Use_hugo = checkbox_val("use_hugo",1,$FromForm);

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
	font-size:10px;
}
.legend
{
	font-size:12px;
}
</style>
<script src="https://d3js.org/d3.v5.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>

</head>
 <body >
<form method="get">
<input type="hidden" name="ft" value="<?php echo $FT ?>">
<input type="hidden" name="crid" value="<?php print $CRID ?>">
<input type="hidden" name="fromform" value="1">
<table width="100%" cellspacing=0 cellpadding=0>
	<tr>
		<td valign="top" align="left">
		<table>
			<tr>
				<td valign="top" align="left"> Factor: <?php print clst_sel("cid",$CID_sel,0,"--choose--") ?> </td>
			</tr>
			<tr>
				<td align="left" valign="top" style="padding-top:6px">
					<table cellspacing=0 cellpadding=0>
						<tr>
							<td align="left" >Min weight:
								 <input name="mw" type="text" size="4" value="<?php print $minWt ?>">
							</td>
							<!--td>Num genes: <input name="ng" type="text" size="4" value="<?php print $numGenes ?>"> 
							</td>
							<td align="left" style="padding-left:20px">Num samples: <input name="ns" type="text" size="4" value="<?php print $numSamps ?>"> 
							</td-->
							<td align="left" style="padding-left:20px" title="Ceiling on expression Z-value (Z = log(expr) normalized by std dev)">Max Z: <input name="maxz" type="text" size="2" value="<?php print $maxZ ?>" > 
							</td>
							<td align="left" style="padding-left:20px" title="<?php print tip_text('hugo_names') ?>">HUGO names:
								 <input name="use_hugo" id="use_hugo_chk" type="checkbox" <?php checked($Use_hugo) ?>>
							</td>
							<td align="left" style="padding-left:20px" ><input type="submit" value="Apply"></td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<td valign="top" align="right" style="font-size:1.4em; padding-right:50px;color:#333333" >
			<span id="popout_btn" title="Open in a new page" style="cursor:pointer">&nbsp;&#9654;&nbsp;</span>
		</td>
	</tr>
</table>
</form>
<?php
if ($CID_sel != 0) 
{  
	echo <<<END
<button>Reset Zoom</button>
END;
}
?>
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

<div id="heatmap" >
<?php
if ($CID_sel != 0) 
{  
	echo "<span id='inprogress'>Drawing heatmap...</span>";
}
?>
</div>

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
	$maxGenes = 0;
	get_heat_data($heat_genes,$heat_samps,$heat_expr,$heat_wts,$samp_strata,
				$CRID,$CID_sel,$minWt,$numGenes,$numSamps,$maxZ,$maxGenes);

	echo <<<END



<script>
var genes = $heat_genes ;
var samps = $heat_samps ;
var vals = $heat_expr ;

//var margin = { top: 50, right: 10, bottom: 50, left: 300 },
var margin = { top: 50, right: 0, bottom: 0, left: 60 },
cellHeight=10;
cellWidth=2;
numRows = genes.length + 1;
numCols = samps.length;
legendHeight = 20;
width = numCols*cellWidth + margin.left + margin.right;
mapHeight = numRows*cellHeight;
height = mapHeight + legendHeight + margin.top + margin.bottom;
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

var geneLabels = svg.append("g")
      .selectAll(".rowLabelg")
      .data(genes)
      .enter()
      .append("text")
      .text(function (d) { return d.lbl; })
      .attr("x", 0)
      .attr("y", function (d, i) {return (d.i)*cellHeight - 1; })
      .style("text-anchor", "end")
      .attr("transform", "translate(-2,0)")
      .attr("class", "axisLabel" )
      ;
var heatMap = svg.append("g").attr("class","g3")
        .selectAll(".cellg")
        .data(vals,function(d){return d.g+":"+d.s;})
        .enter()
        .append("rect")
        .attr("x", function(d) { return d.s * cellWidth; })
        .attr("y", function(d) { return (d.g) * cellHeight; })
        .attr("width", cellWidth)
        .attr("height", cellHeight)
        .style("fill", function(d) { return heatmap_color(d.z,d.g); })
        .style("stroke", function(d) { return heatmap_color(d.z,d.g); })
		.on("mouseover",handleMouseOver)
		.on("mouseout",handleMouseOut)
$( "#inprogress" ).hide();

function handleMouseOver(d,i)
{
	var g = d.g + 1;
	var s = d.s - 1;
	var z = d.z;
	var gname = genes[g].lbl;
	var sname = samps[s].lbl;
	$("#motext").text("gene:" + gname);
	$("#motext2").text("sample:" + sname);
	$("#motext3").text("Z:" + z);
}
function handleMouseOut(d,i)
{
	$("#motext").text("");
	$("#motext2").text("");
	$("#motext3").text("");
}

var colors = [{c:"#ffffff",s:0},{c:"#ff751a",s:1},{c:"#777777",s:2},{c:"#3366ff",s:3}];
var legendRectSize = 10;
var legendSpacing = 10;
var legendItemWidth = legendRectSize + legendSpacing + 60;
var legendY = mapHeight;
var legend = svg.selectAll('.legend')
  .data(colors)
  .enter()
  .append('g')
  .attr('class', 'legend')
  .attr('transform', function(d, i) {
    var horz = i*legendItemWidth;
    var vert = legendY;
    return 'translate(' + horz + ',' + vert + ')';
  });
legend.append("rect")
	.attr('width', legendRectSize)
  .attr('height', legendRectSize)
  .style('fill', function(d){return d.c})
  .style('stroke', function(d){return d.c});
legend.append('text')
  .attr('x', function(d) {return (d.s > 0 ? legendRectSize + legendSpacing : 0);})
  .attr('y', legendRectSize)
  .text(function(d) { return (d.s > 0 ? "R"+d.s : "risk strata:"); });

function heatmap_color(z,g)
{
	if (g > -1)
	{
		return (z >= 0 ? cscalePos(z) : cscaleNeg(z));
	}
	else // colors of the risk strata - sort of a kludj since I put them in the expression array
	{
		if (z == 0)
		{
			return "#777777";
		}
		else
		{
			return (z >= 0 ? "#ff751a" : "#3366ff");
		}
	}
}

var zoom = d3.zoom()
    .scaleExtent([1, 40])
    //.translateExtent([[-100, -100], [width + 90, height + 100]])
    .on("zoom", zoomed);

svg.call(zoom);

function zoomed() {
  	heatMap.attr("transform", d3.event.transform);
  	geneLabels.attr("transform", d3.event.transform.toString() + " translate(-2,0) ");
  	//colLabels.attr("transform", d3.event.transform.toString() + " translate(2,-2) rotate(-90) ");
}
d3.select("button").on("click", resetted);

function resetted() {
  svg.transition()
      .duration(750)
      .call(zoom.transform, d3.zoomIdentity);
}


</script>
<p>Number of genes shown: $numGenes 
END;
if ($maxGenes > $numGenes)
{
	$maxGenesURL = $_SERVER['REQUEST_URI']."&ng=$maxGenes";
	echo <<<END
(<a href="$maxGenesURL">Show all $maxGenes genes</a> meeting weight threshold)
END;
}
echo <<<END
<p>
<table cellspacing=0 cellpadding=0>
	<tr>
		<td width='130' height='15' valign='top'><span id='motext'>&nbsp;</span></td>
		<td width='360' valign='top'><span id='motext2'>&nbsp;</span></td>
		<td valign='top'><span id='motext3'>&nbsp;</span></td>
	</tr>
</table>
END;
}
?>
</body>

<?php

function get_heat_data(&$heat_genes,&$heat_samps,&$heat_expr,&$heat_wts,&$samp_strata,
				$crid, $cid,$minwt,&$numGenes,$maxsamps,$maxZ,&$maxGenes)
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
				" where cid=? order by  clbl asc, sid asc ");
	$st->bind_param("i",$cid);
	$st->bind_result($sid,$sname,$risk_strat);
	$st->execute();
	$snum = 0;	
	while ($st->fetch())
	{
		$samps[] = $sid;
		$sampstrs[] = $sname;
		$z=0;
		if ($risk_strat == 3)
		{
			$z = $maxZ;
		}
		if ($risk_strat == 1)
		{
			$z = -$maxZ;
		}
		$rstrat[] = "{g:-1, s:$snum, z:$z}"; # package this to fit in the heatmap value array
		$snum++;
	}
	$st->close();
	$numSamps = count($samps);

	# get the max number of genes (before numGenes limit)
	$st = $DB->prepare("select count(*) from g2c join glist on glist.id=g2c.gid ".
				" where g2c.cid=? and g2c.wt >= ? ");
	$st->bind_param("id",$cid,$minwt);
	$st->bind_result($maxGenes);
	$st->execute();
	$st->fetch();
	$st->close();
	
	# get the genes in the cluster
	$genestrs[] = "{lbl:\"risk_strat\",i:0}";
	$st = $DB->prepare("select gid,lbl,hugo,wt from g2c join glist on glist.id=g2c.gid ".
				" where g2c.cid=? and g2c.wt >= ? ".
				" order by g2c.mi desc limit $numGenes ");
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
	$heatstrs = $rstrat;
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

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

$pdata = array();
load_proj_data($pdata,$CRID);
$numsamp = $pdata["NUMSAMP"];
$DSID = $pdata["DSID"];

$graph_nodes = "";
$max_day = 0;

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

	get_pair_surv_data($graph_nodes,$max_day);
}
else if ($CID != 0)
{
	$st = $DB->prepare("select survp from clst where id=? ");
	$st->bind_param("i",$CID);
	$st->bind_result($this_survp);
	$st->execute();
	$st->fetch();
	$st->close();

	get_surv_data($graph_nodes,$max_day);
}
if ($CID_pair != "" or $CID != 0)
{
	$tickstr = "";
	$atriskstr = "";
	get_tick_values($max_day,$tickstr,$atriskstr);
}

?>

<head>
<style>
.hiddenline line{
	stroke:white;
}
.hiddenline path{
	stroke:white;
}
.svg-container {
    display: inline-block;
    position: relative;
    width: 95%;
    padding-bottom: 95%; /* aspect ratio */
    vertical-align: top;
    overflow: hidden;
}
.svg-content-responsive {
    display: inline-block;
    position: absolute;
    top: 10px;
    left: 0;
}
.legend
{
	font-size:12px;
}
.coordbox
{
	font-size:12px;
}
rect
{
	stroke-width:1;
}
</style>

<script src="https://d3js.org/d3.v5.min.js"></script>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
</head>
 <body >

<form id="selform" method="get">
<input type="hidden" name="ft" value="<?php echo $FT ?>">
<input type="hidden" name="crid" value="<?php print $CRID ?>">
<input type="hidden" name="fromform" value="1">
<table width="100%" cellspacing=0 cellpadding=0>
	<tr>
		<td valign="top" align="left">
			<table cellspacing=0 cellpadding=0 >
				<tr>
					<td align="left" valign="top">
						<table cellspacing=1 cellpadding=0>
						<tr>
							<td>Single Factor: <td>
						</tr>
						<tr>
							<td><?php print clst_sel_surv("cid",$CID,$CID2) ?> </td>
						</tr>
						</table>
					</td>
					<td align="left" valign="top" style="padding-left:10px">
						<table cellspacing=1 cellpadding=0>
						<tr>
							<td>Paired Factors:</td>
						</tr>
						<tr>
							<td><?php print clst_sel_pair("pair",$CID,$CID2) ?> </td>
						</tr>
						</table>
					</td>
					<td align="left" valign="top" style="padding-left:10px">
						<table cellspacing=1 cellpadding=0>
						<tr>
							<td>Sort by p-val:</td>
						</tr>
						<tr>
							<td><input name="pvalsort" id="chk_pvalsort" type="checkbox" <?php checked($Pvalsort) ?>></td>
						</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
		<td valign="top" align="right" style="font-size:1.4em; padding-right:50px;color:#333333" >
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
	//setTimeout(function(){ $('#selform').submit(); }, 100);
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


<div id="coords"></div>
<div  id="graph" ></div>
<?php 
$survp_disp = sprintf("%1.2E",$this_survp);
?>

<script>
function link_clr(strat)
{
	if (strat == 1)
	{
		return "#ff751a";
	}
	if (strat == 2)
	{
		return "#777777";
	}
	if (strat == 3)
	{
		return "#3366ff";
	}
	return "black";
}
var nodes2 = <?php echo $graph_nodes ?>  // array of {x=months, y=survival, s=stratum}
var links = new Array();
var prev_strat = nodes2[0].s;
var max_months = 0;
for (var i = 1; i < nodes2.length; i++)
{
	if (nodes2[i].s == prev_strat)
	{
		links.push({source:nodes2[i-1], target:nodes2[i], clr:link_clr(nodes2[i].s)});
	}
	if (nodes2[i].x > max_months)
	{
		max_months = nodes2[i].x;
	}
	prev_strat = nodes2[i].s;
}
/*
// set up approximately 5 ticks, but each one a multiple of 10 months
ticksize = max_months/5.0;
ticksize = 10*Math.floor(ticksize/10);
var ticks = new Array();
for (t = ticksize; t <= max_months; t += ticksize)
{
	ticks.push(t);
}
*/
<?php echo $tickstr ?>
var atrisk = new Array();
<?php echo $atriskstr ?>

var margin = {top: 20, right: 20, bottom: 100, left: 50},
    width = 600 - margin.left - margin.right,
    height = 400 - margin.top - margin.bottom;

// create the svg with params making it auto-size to the window size
var vis = d3.select("#graph")
	.append("div")
	.classed("svg-container",true)
	.append("svg")
	.on("mousemove", mousemove)
	.on("mouseout", mouseout)
	.attr("preserveAspectRatio", "xMinYMin meet")
	.attr("viewBox", "0 0 600 400")
	.classed("svg-content-responsive",true)
	.append("g")
	.attr("transform", "translate(" + margin.left + "," + margin.top + ")")
	;

var coordbox = vis.selectAll('.coordbox')
  .data([0])
  .enter()
  .append('g')
  .attr('class', 'coordbox')
  .attr('transform',  'translate(300,100)');
coordbox.append('text')
  .attr('x', 0)
  .attr('y', 0)
  .attr('id','coordbox')
  .text("");


function mousemove() {
    var time = Math.round(xscale.invert(d3.mouse(this)[0] - margin.left));
    var surv = Math.round(100*yscale.invert(d3.mouse(this)[1] - margin.top))/100;
	var str = time + "," + surv;
	if (time < 0 || surv < 0 || surv > 1)
	{
		str = "";
	}
	$("#coordbox").text(str);
}
function mouseout() {
	$("#coordbox").text("");
}

var xscale = d3.scaleLinear().range([0,width]);
var yscale = d3.scaleLinear().range([height,0]);

xscale.domain([0, d3.max(nodes2, function(d) { return d.x; })]);
yscale.domain([0, d3.max(nodes2, function(d) { return d.y; })]);


vis.selectAll(".line")
	.data(links)
	.enter()
	.append("line")
	.attr("x1", function(d) { return xscale(d.source.x )})
	.attr("y1", function(d) { return yscale(d.source.y )})
	.attr("x2", function(d) { return xscale(d.target.x )})
	.attr("y2", function(d) { return yscale(d.target.y )})
	.style("stroke", function(d) { return d.clr});

var xAxis = d3.axisBottom()
    .scale(xscale)
	.tickValues(ticks);

vis.append("g")
      .attr("transform", "translate(0," + height + ")")
      .call(xAxis);

// Add another axis with same tick vector but substituting different text, for At-Risk
var xAxis2 = d3.axisBottom()
    .scale(xscale)
	.tickValues(ticks)
	.tickFormat(function(n) { return atrisk[n]})

vis.append("g")
    .attr("transform", "translate(0," + (height + 60) + ")")
	.attr("class", "hiddenline")
    .call(xAxis2);

vis.append("g")
    .call(d3.axisLeft(yscale));

vis.append("text")             
      .attr("transform", "translate(" + (width/2) + " ," + (height + margin.top + 20) + ")")
      .style("text-anchor", "middle")
      .text("Months");
vis.append("text")             
      .attr("transform", "translate(" + (width/2) + " ," + (height + margin.top + 75) + ")")
      .style("text-anchor", "middle")
	  .style("font-size","14px")
      .text("At-risk");

var colors = [{c:"#ff751a",s:1},{c:"#777777",s:2},{c:"#3366ff",s:3}];
var legendRectSize = 10;
var legendSpacing = 10;
var legend = vis.selectAll('.legend')
  .data(colors)
  .enter()
  .append('g')
  .attr('class', 'legend')
  .attr('transform', function(d, i) {
    var height = legendRectSize + legendSpacing;
    var horz = 300;
    var vert =  10+ i*height;
    return 'translate(' + horz + ',' + vert + ')';
  });
legend.append("rect")
	.attr('width', legendRectSize)
	.attr('height', legendRectSize)
	.style('fill', function(d){return d.c})
	.style('stroke', function(d){return d.c});
legend.append('text')
  .attr('x', legendRectSize + legendSpacing)
  .attr('y', legendRectSize)
  .text(function(d) { return "R"+d.s; });

var legendlbl = vis.selectAll('.legendlbl')
  .data([0])
  .enter()
  .append('g')
  .attr('class', 'legend')
  .attr('transform',  'translate(300,0)');
legendlbl.append('text')
  .attr('x', 0)
  .attr('y', 0)
  .text('risk strata:');

</script>

</body>

<?php

##################################################################

function get_surv_data(&$nodestr,&$max_day)
{
	global $CID,$DB;

	$st = $DB->prepare("select dte,strat,surv from survdt where cid=? order by strat asc, dte asc");
	$st->bind_param("i",$CID);
	$st->bind_result($time,$strat,$surv);
	$st->execute();
	$prev_strat = -1;
	$nodes = array();
	$max_day = 0;
	while ($st->fetch())
	{
		if ($strat != $prev_strat)
		{
			$nodes[] = "{x:0, y:1.0, s:$strat}";
		}
		$prev_strat = $strat;
		if ($time > $max_day)
		{
			$max_day = $time;
		}
		$time /= 30.0;
		$nodes[] = "{x:$time, y:$surv, s:$strat}";
	}
	$st->close();

	$nodestr = "[".implode(",\n",$nodes)."];";
}

######################################################################

function get_pair_surv_data(&$nodestr,&$max_day)
{
	global $CID, $CID2, $DB;

	$st = $DB->prepare("select dte,strat,surv from pair_survdt where cid1=? and cid2=? order by strat asc, dte asc");
	$st->bind_param("ii",$CID,$CID2);
	$st->bind_result($time,$strat,$surv);
	$st->execute();
	$prev_strat = -1;
	$nodes = array();
	$max_day = 0;
	while ($st->fetch())
	{
		if ($strat != $prev_strat)
		{
			$nodes[] = "{x:0, y:1.0, s:$strat}";
		}
		$prev_strat = $strat;
		if ($time > $max_day)
		{
			$max_day = $time;
		}
		$time /= 30.0;
		$nodes[] = "{x:$time, y:$surv, s:$strat}";
	}
	$st->close();
	$nodestr = "[".implode(",\n",$nodes)."];";
}

################################################################

function get_tick_values($max_day, &$tickstr,&$atriskstr)
{
	global $CRID, $DSID, $DB;
	$max_month = $max_day/30;
	$ticksize_approx = $max_month/(4.5);  # ~4 ticks
	$ticksize = 10*floor($ticksize_approx/10);	
	$ticks = array();
	for ($t = 0; $t <= $max_month; $t += $ticksize)
	{
		$ticks[] = $t;
	}
	$tickstr = "[".implode(",",$ticks)."]";
	$tickstr = "var ticks = $tickstr ;";
	
	# now we set up javascript to map the tick times to their
	# at risk values
	# We have to compute the atrisk number for each tick
	$atrisk = array();
	$query = "select count(*) as cnt from samp join sampdt on sampdt.SID=samp.ID ".
					" where samp.DSID=? and (sampdt.dtd >= ?  or (sampdt.dtlc >= ? or sampdt.dtlc = -1))";
	$st = $DB->prepare($query);
	$st->bind_result($atrisknum);
	foreach ($ticks as $t)
	{
		$day = $t*30;
		$st->bind_param("iii",$DSID,$day,$day);
		$st->execute();
		$st->fetch();
		$atrisk[] = "atrisk[$t] = $atrisknum;";
	}
	$atriskstr = implode(" ",$atrisk);
	$st->close();

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
		$pval = sprintf("%1.0E",$pval);
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
		$pval = sprintf("%1.0E",$pval);
		$opts[] = "<option value='$opt_val' $selected>$opt_lbl (p=$pval)</option>";
	}
	$st->close();
	return "<select name='$name' id='sel_$name'>\n".implode("\n",$opts)."\s</select>\n";
}



?>

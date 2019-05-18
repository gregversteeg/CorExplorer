<?php
require_once("util.php");

#
# Generates the graph comparing GO annotation levels
#

$selected_ids = array();

$crid2name = array();
$s = dbps("select id,lbl from clr where hideme=0");
$s->bind_result($crid,$pname);
$s->execute();
while ($s->fetch())
{
	if (checkbox_val("ID$crid",0))
	{
		check_read_access($crid);
		$selected_ids[$crid] = 1;
	}
	if (!read_access($crid))
	{
		continue;
	}
	$crid2name[$crid] = $pname;
}
$s->close();

for($def_id = 0; ; $def_id++)
{
	if (isset($crid2name[$def_id]))
	{
		break;
	}
}

if (count($selected_ids) == 0)
{
	$selected_ids[$def_id] = 1;
}

$vars = array();   # variables = selected runs
$evals = array();

$max_eval = 0;
$graph_pts = array();
$names = array();
foreach ($selected_ids as $crid => $foo)
{
	$evals[$crid] = array();
	$s = dbps("select eval, count(*) from (select clst.lbl as cnum, ".
			" max(floor(-log10(ifnull(clst2go.pval,1)))) as eval from clst ".
			" left join clst2go on clst2go.cid=clst.id ".
			" where clst.crid=$crid and lvl=0 group by clst.id ) as tbl ".
			"  group by tbl.eval order by eval desc ");
	$s->bind_result($eval,$count);
	$s->execute();
	while ($s->fetch())
	{
		$evals[$crid][$eval]  = $count;	
		if ($eval > $max_eval)
		{
			$max_eval = $eval;
		}
	}
	$pname = $crid2name[$crid];
	$names[] = $pname;
	$s->close();
}

for ($n = 0; $n <= $max_eval; $n++)
{
	foreach ($selected_ids as $crid => $foo)
	{
		if (!isset($evals[$crid][$n]))
		{
			$evals[$crid][$n] = 0;
		}
	}
}
$idx = 0;
$idx2crid = array();
$max_count = 0;
foreach ($selected_ids as $crid => $foo)
{
	for ($n = $max_eval-1; $n >= 0; $n--)
	{
		$evals[$crid][$n] += $evals[$crid][$n+1];	
		$graph_pts[] = "{i:$idx,e:$n,c:".$evals[$crid][$n]."}";
	}
	if ($evals[$crid][0] > $max_count)
	{
		$max_count = $evals[$crid][0];
	}
	$idx2crid[$idx] = $crid;
	$idx++;
}


$head_xtra = <<<END
<script src="https://d3js.org/d3.v5.min.js"></script>
END;

head_section("GO Annotation Compare", $head_xtra);
body_start();
?>

<script type="text/javascript" src="http://www.canvasxpress.org/js/canvasXpress.min.js"></script>
<h3>Compare GO Annotation Levels</h3>
Graph shows cumulative count of factors having best GO FDR value less than or equal to 
a given value.
<p>
<table>
	<tr>
		<td valign="top">
			<div  id="graph" style="width:900px;height:700px" ></div>
		</td>
		<td valign="top" style="padding-left:30px">
<?php dump_checkboxes(); ?>
		</td>
	</tr>
</table>
<script>

var clr_list = ['#e6194b', '#3cb44b', '#ffe119', '#4363d8', '#f58231', '#911eb4', '#46f0f0', '#f032e6', '#bcf60c', '#fabebe', '#008080', '#e6beff', '#9a6324', '#fffac8', '#800000', '#aaffc3', '#808000', '#ffd8b1', '#000075', '#808080', '#ffffff', '#000000'];

function link_clr(idx)
{
	return clr_list[idx];
}
var proj_names = <?php echo "['".implode("','",$names)."']" ?>;
var num_projects = proj_names.length;
var colors = clr_list.slice(0,num_projects);
var nodes = <?php echo "[".implode(",",$graph_pts)."]" ?>;
var max_eval = <?php echo $max_eval ?>;
var max_count = <?php echo $max_count ?>;

var links = new Array();
var prev_i = nodes[0].i;
for (var i = 1; i < nodes.length; i++)
{
	if (nodes[i].i == prev_i)
	{
		links.push({source:nodes[i-1], target:nodes[i], clr:link_clr(prev_i)});
	}
	prev_i = nodes[i].i;
}
var margin = {top: 20, right: 20, bottom: 100, left: 50},
    width = 600 - margin.left - margin.right,
    height = 400 - margin.top - margin.bottom;

var vis = d3.select("#graph")
	.append("svg")
	.attr("viewBox", "0 0 600 400")
	.append("g")
	.attr("transform", "translate(" + margin.left + "," + margin.top + ")")
	;

var xscale = d3.scaleLinear().range([0,width]);
var yscale = d3.scaleLinear().range([height,10]);

xscale.domain([0,max_eval]);
yscale.domain([0,max_count]);

var eval_ticks = [];
for (e = 10; e < max_eval; e+= 10)
{
	eval_ticks.push(e);
}
var cnt_ticks = [];
for (c = 50; c <= max_count; c+= 50)
{
	cnt_ticks.push(c);
}

vis.selectAll(".line")
	.data(links)
	.enter()
	.append("line")
	.attr("x1", function(d) { return xscale(d.source.e )})
	.attr("y1", function(d) { return yscale(d.source.c )})
	.attr("x2", function(d) { return xscale(d.target.e )})
	.attr("y2", function(d) { return yscale(d.target.c )})
	.style("stroke", function(d) { return d.clr});

var xAxis = d3.axisBottom()
    .scale(xscale)
	.tickValues(eval_ticks);

vis.append("g")
      .attr("transform", "translate(0," + height + ")")
      .call(xAxis);

vis.append("text")             
      .attr("transform", "translate(" + (width/2) + " ," + (height + margin.top + 20) + ")")
      .style("text-anchor", "middle")
      .text("Best GO FDR (-log10(FDR)");

var yAxis = d3.axisLeft()
    .scale(yscale)
	.tickValues(cnt_ticks);

vis.append("g")
      .call(yAxis);

vis.append("text")             
    .style("text-anchor", "middle")
    .text("# Factors");

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
	.style('fill', function(d){return d})
	.style('stroke', function(d){return d});
legend.append('text')
  .attr('x', legendRectSize + legendSpacing)
  .attr('y', legendRectSize)
  .text(function(d,i) { return proj_names[i]; });
</script>

<?php

body_end();

function dump_checkboxes()
{
	global $crid2name, $selected_ids;

	echo <<<END
<form>
<table>
END;
	foreach ($crid2name as $crid => $name)
	{
		$lbl = "ID$crid";
		$checked = (isset($selected_ids[$crid]) ? " checked='checked' " : "");
		echo <<<END
<input type="checkbox" name="$lbl" $checked>&nbsp;$name
<br>
END;
	
	}
	echo <<<END
<input type="submit" value="Submit">
</form>
</table>
END;
}


?>

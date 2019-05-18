<?php
require_once("util.php");

$selected_ids = array();

$crid2name = array();
$s = dbps("select id,lbl from clr where hideme=0");
$s->bind_result($crid,$pname);
$s->execute();
while ($s->fetch())
{
	if (checkbox_val("ID$crid",0))
	{
		check_read_access($crid); # script dies here if non-accessible
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

$tc_vals = array();

$max_cnum = 0;
$max_tc = 0;
$min_tc = 0;
$graph_pts = array();
$idx = 0;
$idx2crid = array();
$names = array();
foreach ($selected_ids as $crid => $foo)
{
	$idx2crid[$idx] = $crid;
	$names[] = $crid2name[$crid];
	$tc_vals[$crid] = array();
	$s = dbps("select lbl, tc from clst where crid=$crid and lvl=0");
	$s->bind_result($cnum,$tc);
	$s->execute();
	while ($s->fetch())
	{
		$tc = .1*floor(10*$tc);
		$tc_vals[$crid][$cnum]  = $tc;	
		$graph_pts[] = "{c:$cnum,tc:$tc,i:$idx}";
		if ($cnum > $max_cnum)
		{
			$max_cnum = $cnum;
		}
		if ($tc > $max_tc)
		{
			$max_tc = $tc;
		}		
		if ($tc < $min_tc)
		{
			$min_tc = $tc;
		}		
	}
	$idx++;
	$pname = $crid2name[$crid];
	$s->close();
}


foreach ($selected_ids as $crid => $foo)
{
	$this_data = array();
	for ($n = 0; $n <= $max_cnum; $n++)
	{
		if (isset($tc_vals[$crid][$n]))
		{
			$this_data[] = $tc_vals[$crid][$n];
		}
		else
		{
			$this_data[] = 0;
		}
	}	
}

$head_xtra = <<<END
<script src="https://d3js.org/d3.v5.min.js"></script>
END;

head_section("TC Compare", $head_xtra);
body_start();
?>

<h3>Compare Total Correlation</h3>
Graph shows total correlation (TC) for each factor. Note that CorEx numbers factors in <br>
descending order of TC. 
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
var max_cnum = <?php echo $max_cnum ?>;
var max_tc = <?php echo $max_tc ?>;
var min_tc = <?php echo $min_tc ?>;

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
var yscale = d3.scaleLinear().range([height,0]);

xscale.domain([0,max_cnum]);
yscale.domain([min_tc, max_tc]);

var tc_ticks = [];
var num_tc_ticks = Math.floor(max_tc/40);
for (i = 0; i <= num_tc_ticks; i++)
{
	tc_ticks.push(40*i);
} 

vis.selectAll(".line")
	.data(links)
	.enter()
	.append("line")
	.attr("x1", function(d) { return xscale(d.source.c )})
	.attr("y1", function(d) { return yscale(d.source.tc )})
	.attr("x2", function(d) { return xscale(d.target.c )})
	.attr("y2", function(d) { return yscale(d.target.tc )})
	.style("stroke", function(d) { return d.clr});

var xAxis = d3.axisBottom()
    .scale(xscale)
	.tickValues([40,80,120,160]);

vis.append("g")
      .attr("transform", "translate(0," + height + ")")
      .call(xAxis);

vis.append("text")             
      .attr("transform", "translate(" + (width/2) + " ," + (height + margin.top + 20) + ")")
      .style("text-anchor", "middle")
      .text("Factor");

var yAxis = d3.axisLeft()
    .scale(yscale)
	.tickValues(tc_ticks);

vis.append("g")
      .call(yAxis);

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

##################################################################

function dump_checkboxes()
{
	global $crid2name, $selected_ids;

	echo <<<END
<form>
<table>
<tr><td><b>Select projects:</b></td></tr>
END;
	foreach ($crid2name as $crid => $name)
	{
		$lbl = "ID$crid";
		$checked = (isset($selected_ids[$crid]) ? " checked='checked' " : "");
		echo <<<END
<tr><td><input type="checkbox" name="$lbl" $checked>&nbsp;$name</td></tr>
END;
	
	}
	echo <<<END
<tr><td><input type="submit" value="Submit"></td></tr>
</table>
</form>
END;
}


?>

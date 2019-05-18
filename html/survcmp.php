<?php
require_once("util.php");

$selected_ids = array();

$max_pval = 0.1;

$crid2name = array();
$s = dbps("select id,lbl from clr where hideme=0");
$s->bind_result($crid,$pname);
$s->execute();
while ($s->fetch())
{
	if (checkbox_val("ID$crid",0))
	{
		$selected_ids[$crid] = 1;
		check_read_access($crid);
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

$pvals = array();

$max_cnum = 0;
$min_eval = 1;
$max_eval = 0;
$graph_pts = array();
$names = array();
foreach ($selected_ids as $crid => $foo)
{
	$pvals[$crid] = array();
	$s = dbps("select lbl, -log10(survp) as pval from clst ".
			" where crid=$crid and lvl=0 and survp <= $max_pval order by pval desc");
	$s->bind_result($cnum,$pval);
	$s->execute();
	while ($s->fetch())
	{
		$pvals[$crid][]  = $pval;	
		if ($pval < $min_eval)
		{
			$min_eval = $pval;
		}
		if ($pval > $max_eval)
		{
			$max_eval = $pval;
		}
	}
	$pname = $crid2name[$crid];
	$names[] = $pname;
	$s->close();
	if (count($pvals[$crid]) > $max_cnum)
	{
		$max_cnum = count($pvals[$crid]);
	}
	$idx++;
}


$idx = 0;
foreach ($selected_ids as $crid => $foo)
{
	$this_data = array();
	for ($n = 0; $n <= $max_cnum; $n++)
	{
		if (isset($pvals[$crid][$n]))
		{
			$graph_pts[] = "{i:$idx,n:$n,e:".$pvals[$crid][$n]."}";
		}
		else
		{
			$graph_pts[] = "{i:$idx,n:$n,e:$min_eval}";
		}
	}	
	$idx++;
}

$head_xtra = <<<END
<script src="https://d3js.org/d3.v5.min.js"></script>
END;

head_section("Survival Differential Compare", $head_xtra);
body_start();
?>

<h3>Compare Survival Differential (E-value)</h3>
Graph shows survival p-values plotted against factor number, where the 
factors are ordered in ascending order of p-value (descending order of <br>
significance), up to a p-value cuttof <?php echo $max_pval ?>.
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
var min_eval = <?php echo $min_eval ?>;
var max_eval = <?php echo $max_eval ?>;
var max_cnum = <?php echo $max_cnum ?>;

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

xscale.domain([0,max_cnum]);
yscale.domain([min_eval, max_eval]);

vis.selectAll(".line")
	.data(links)
	.enter()
	.append("line")
	.attr("x1", function(d) { return xscale(d.source.n )})
	.attr("y1", function(d) { return yscale(d.source.e )})
	.attr("x2", function(d) { return xscale(d.target.n )})
	.attr("y2", function(d) { return yscale(d.target.e )})
	.style("stroke", function(d) { return d.clr});

var xticks = [];
for (c = 5; c < max_cnum; c+=5)
{
	xticks.push(c);
}
var yticks = [];
for (e = 1; e <= max_eval; e++)
{
	yticks.push(e);
}
var xAxis = d3.axisBottom()
    .scale(xscale)
	.tickValues(xticks);

vis.append("g")
      .attr("transform", "translate(0," + height + ")")
      .call(xAxis);

vis.append("text")             
      .attr("transform", "translate(" + (width/2) + " ," + (height + margin.top + 20) + ")")
      .style("text-anchor", "middle")
      .text("Factor");

var yAxis = d3.axisLeft()
    .scale(yscale)
	.tickValues(yticks);

vis.append("g")
      .call(yAxis);

vis.append("text")             
    .style("text-anchor", "middle")
    .text("-log10(pvalue)");

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

###########################################################################

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

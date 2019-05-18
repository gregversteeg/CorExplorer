<?php
require_once("util.php");

#
# Compare factor annotions in different CorEx datasets using the 
# Rank-Biased Overlap method of Webber, Moffat, and Zobel:
#
# A similarity measure for indefinite rankings
# ACM Transactions on Information Systems 
# Volume 28 Issue 4, November 2010

$selected_ids = array();

$Lbltype = getval("lbltype","hugo");
$Use_shared = checkbox_val("shared",0);

$crid1 = getint("crid1",0);
$crid2 = getint("crid2",0);

$shared_checked = ($Use_shared ? " checked='checked' " : "");

$head_xtra = "";
if ($crid1 != 0)
{
	check_read_access($crid1);
	check_read_access($crid2);
	# don't load if not needed
	$head_xtra = <<<END
<script src="https://d3js.org/d3.v5.min.js"></script>
END;
}

head_section("RBO Compare", $head_xtra);
body_start();
?>


<?php
if (count($selected_ids) != 0 && count($selected_ids) != 2)
{
	print "<p>Please select two projects<p>";
}
?>

<h3>Compare Factors using Rank-Biased Overlap (RBO)<sup>1</sup> </h3>
Factors are compared based on ranked gene content, with genes ranked by weight. 
<br>
Graph shows the best RBO match value for each factor in each project. Factors
are ordered in descending order by best match. 
<br>
Table shows the best and second-best match for each factor, and their top GO annotations. 
<p>
<table>
	<tr>
		<td valign="top">
<?php dump_results() ?>
		</td>
		<td valign="top" style="padding-left:30px">
			<form>
			<table cellspacing=8>
				<tr>
					<td><b>Choose projects to compare:</b></td>
				</tr>
				<tr>
					<td> Project 1: <?php print run_sel("crid1",$crid1); ?>
					</td>
				</tr>
				<tr>
					<td> Project 2: <?php print run_sel("crid2",$crid2); ?>
					</td>
				</tr>
				<tr>
					<td> <input type="checkbox" name="shared" <?php echo $shared_checked ?>> 
						Use only genes contained in both projects 
						
					</td>
				</tr>
				<tr>
					<td> <input type="submit" value="submit"></td>
				</tr>
			</table>
			</form>
		</td>
	</tr>
	<tr>
		<td colspan="2" align="left">
<sup>1</sup>
Webber,W., Moffat,A., Zobel,J.
<br>
<b>A similarity measure for indefinite rankings</b>
<br>
ACM Transactions on Information Systems Volume 28 Issue 4, November 2010

		</td>
	</tr>
</table>

<?php
body_end();

####################################################################

function dump_results()
{
	global $selected_ids, $Lbltype, $crid1, $crid2,$Use_shared;
	if ($crid1 == 0 || $crid2 == 0)
	{
		return;
	}
	$groups1 = array();
	$groups2 = array();
	$cid2info = array();
	$s = dbps("select id,lbl from clst where crid=? and lvl=0");
	$s->bind_param("i",$crid1);
	$s->bind_result($cid,$lbl);
	$s->execute();
	while ($s->fetch())
	{
		$groups1[] = $cid;
		$cid2info[$cid] = array("lbl" => $lbl, "crid" => $crid1);
	}
	$s->close();
	$s = dbps("select id,lbl from clst where crid=? and lvl=0");
	$s->bind_param("i",$crid2);
	$s->bind_result($cid,$lbl);
	$s->execute();
	while ($s->fetch())
	{
		$groups2[] = $cid;
		$cid2info[$cid] = array("lbl" => $lbl, "crid" => $crid2);
	}
	$s->close();
	#
	# Get the top annotations for each
	#
	foreach ($cid2info as $cid => $arr)
	{
		$crid = $arr["crid"];
		$s = dbps("select gos.term,gos.descr from clst2go join gos on gos.term=clst2go.term ".
				" where cid=$cid and gos.crid=$crid order by pval asc limit 1");
		$s->bind_result($term, $desc);
		$s->execute();
		while ($s->fetch())
		{
			$cid2info[$cid]["term"] = $term;
			$cid2info[$cid]["desc"] = $desc;
		}
		$s->close();
	}
	#	
	# Figure out the pairs we are going to look at. 
	# These are the groups that share at least one topN gene.
	#
	$topN = 30;
	$cid2genes1 = array();
	foreach ($groups1 as $cid)
	{
		$cid2genes1[$cid] = array();
		$s = dbps("select lbl from g2c join glist on glist.id=g2c.gid where cid=? order by wt desc limit $topN");
		$s->bind_param("i",$cid);
		$s->bind_result($lbl);
		$s->execute();
		while ($s->fetch())
		{
			$lbl = preg_replace("/\..*/","",$lbl);  # remove pesky suffixes
			$cid2genes1[$cid][] = $lbl;	
		}
		$s->close();
	}	
	$genes2cid2 = array();
	foreach ($groups2 as $cid)
	{
		$s = dbps("select lbl from g2c join glist on glist.id=g2c.gid where cid=? order by wt desc limit $topN");
		$s->bind_param("i",$cid);
		$s->bind_result($lbl);
		$s->execute();
		while ($s->fetch())
		{
			$lbl = preg_replace("/\..*/","",$lbl);
			if (!isset($genes2cid2[$lbl]))
			{
				$genes2cid2[$lbl] = array();
			}
			$genes2cid2[$lbl][] = $cid;
		}
		$s->close();
	}	
	#
	# Now compute the RBO score for each pair
	# Using (if specified) only genes that are in both projects
	#

	#
	# If we're using shared genes, get the project gene lists
	#
	$pinfo1 = array(); $pinfo2 = array();
	load_proj_data($pinfo1,$crid1);
	load_proj_data($pinfo2,$crid2);
	$glid1 = $pinfo1["GLID"];
	$glid2 = $pinfo2["GLID"];
	$all_genes1 = array();
	$all_genes2 = array();
	if ($Use_shared)
	{
		$s = dbps("select lbl from glist where glid=$glid1");
		$s->bind_result($lbl);
		$s->execute();
		while ($s->fetch())
		{
			$lbl = preg_replace("/\..*/","",$lbl);
			$all_genes1[$lbl] = 1;
		}
		$s->close();
		$s = dbps("select lbl from glist where glid=$glid2");
		$s->bind_result($lbl);
		$s->execute();
		while ($s->fetch())
		{
			$lbl = preg_replace("/\..*/","",$lbl);
			$all_genes2[$lbl] = 1;
		}
		$s->close();
	}

	$genes2 = array(); # store proj 2 factor gene lists as we get them
	$results = array();
	$results2 = array();
	$max_rbos1 = array();
	foreach ($groups1 as $cid1)
	{
		$genes1 = array();
		$results[$cid1] = array();
		get_genelist($cid1,$genes1,$all_genes2);
		$done = array();
		$max_rbo = 0;
		foreach ($cid2genes1[$cid1] as $lbl)
		{
			if (isset($genes2cid2[$lbl]))
			{
				foreach ($genes2cid2[$lbl] as $cid2)
				{
					if (isset($done[$cid2]))
					{
						continue;
					}
					$done[$cid2] = 1;
					if (!isset($genes2[$cid2]))
					{
						$genes2[$cid2] = array();
						get_genelist($cid2,$genes2[$cid2],$all_genes1);
					}
					$rbo = sprintf("%.2f",rbo_score($genes1,$genes2[$cid2]));
					if ($rbo > $max_rbo)
					{
						$max_rbo = $rbo;
					}
					$results[$cid1][$cid2]= $rbo;
					$results2[$cid2][$cid1]= $rbo;
				}
			}
		}
		$max_rbos1[] = $max_rbo;
	}
	$max_rbos2 = array();
	$best_reverse_match = array();
	foreach ($groups2 as $cid2)
	{
		$max_rbo = 0;
		$best_cid1 = 0;
		if (isset($results2[$cid2]))
		{
			foreach ($results2[$cid2] as $cid1 => $rbo)
			{
				if ($rbo > $max_rbo)
				{
					$max_rbo = $rbo;
					$best_cid1 = $cid1;
				}
			}	
		}
		$max_rbos2[] = $max_rbo;
		$best_reverse_match[$cid2] = $best_cid1;
	}

	$pname1 = $pinfo1["lbl"];
	$pname2 = $pinfo2["lbl"];

	#
	# Set up the graph data
	#
	$graph_pts = array();

	# Some hoops in case number of groups differ
	$nsamps = max(count($max_rbos1),count($max_rbos2));
	for ($i = count($max_rbos1); $i < $nsamps; $i++)
	{
		$max_rbos1[] = 0;
	}
	for ($i = count($max_rbos2); $i < $nsamps; $i++)
	{
		$max_rbos2[] = 0;
	}
	arsort($max_rbos1);
	arsort($max_rbos2);
	$max_rbo = 0;
	$n = 0;
	foreach ($max_rbos1 as $foo => $rbo)
	{
		$graph_pts[] = "{i:0,n:$n,rbo:$rbo}";
		$n++;
		if ($rbo > $max_rbo)
		{
			$max_rbo = $rbo;
		}
	}
	$n = 0;
	foreach ($max_rbos2 as $foo => $rbo)
	{
		$graph_pts[] = "{i:1,n:$n,rbo:$rbo}";
		$n++;
		if ($rbo > $max_rbo)
		{
			$max_rbo = $rbo;
		}
	}
	$nodestr = "[".implode(",",$graph_pts)."]";
echo <<<END
			<div  id="graph" style="width:900px;height:600px" ></div>
<script>


var clr_list = ["red","blue"];
var proj_names = ["$pname1","$pname2"];

function link_clr(idx)
{
	return clr_list[idx];
}
var nodes = $nodestr;
var max_rbo = $max_rbo;
var max_cnum = $nsamps;

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
yscale.domain([0, max_rbo]);

vis.selectAll(".line")
	.data(links)
	.enter()
	.append("line")
	.attr("x1", function(d) { return xscale(d.source.n )})
	.attr("y1", function(d) { return yscale(d.source.rbo )})
	.attr("x2", function(d) { return xscale(d.target.n )})
	.attr("y2", function(d) { return yscale(d.target.rbo )})
	.style("stroke", function(d) { return d.clr});

var xticks = [];
for (c = 40; c <= max_cnum; c += 40)
{
	xticks.push(c);
}
var yticks = [];
for (e = 0; e <= max_rbo; e += 0.2)
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
    .text("Best RBO");

var legendRectSize = 10;
var legendSpacing = 10;
var legend = vis.selectAll('.legend')
  .data(clr_list)
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
END;

	#
	# Print the table
	#
	print "<table border=true rules=all cellpadding=3 >\n";
	print "<tr><td colspan=2 align=center><b>$pname1</b></td><td colspan=4 align=center><b>$pname2</b></td></tr>\n";
	print "<tr><td>Factor</td><td>Annotation</td><td>Best&nbsp;Match<sup>*</sup></td><td>RBO score</td><td>Annotation</td><td>Second Match</td><td>RBO score</td><td>Annotation</td></tr>\n";
	foreach ($results as $cid => $arr)
	{
		arsort($arr);
		$cids = array_keys($arr);
		$cid1 = "";
		$rbo1 = "";
		$clink1 = "";
		$revtext = "";
		$revstyle = "";
		if (count($arr) > 0)
		{
			$cid1 = $cids[0];
			$rbo1 = $arr[$cid1];
			$lbl1 = $cid2info[$cid1]["lbl"];
			$best_reverse = $best_reverse_match[$cid1];
			if ($best_reverse != $cid)
			{
				$br_lbl = $cid2info[$best_reverse]["lbl"];
				$brscore = $results2[$cid1][$best_reverse];
				$revtext = "&nbsp;($br_lbl:$brscore)";
				$revstyle = "background-color:#f5f5f5;";
			}
			$clink1 = "<a href='/explorer.html?crid=$crid2&cid=$cid1' target='_blank'>$lbl1</a>";
		}
		$cid2 = "";
		$rbo2 = "";
		$clink2 = "";
		if (count($arr) > 1)
		{
			$cid2 = $cids[1];
			$rbo2 = $arr[$cid2];
			$lbl2 = $cid2info[$cid2]["lbl"];
			$clink2 = "<a href='/explorer.html?crid=$crid2&cid=$cid2' target='_blank'>$lbl2</a>";

		}
		$lbl = $cid2info[$cid]["lbl"];
		$clink = "<a href='/explorer.html?crid=$crid1&cid=$cid' target='_blank'>$lbl</a>";
		$annot = "";
		if (isset($cid2info[$cid]["term"]))
		{
			$annot = $cid2info[$cid]["desc"];	
		}
		$annot1 = "";
		if (isset($cid2info[$cid1]["term"]))
		{
			$annot1 = $cid2info[$cid1]["desc"];	
		}
		$annot2 = "";
		if (isset($cid2info[$cid2]["term"]))
		{
			$annot2 = $cid2info[$cid2]["desc"];	
		}
		print "<tr ><td>$clink</td><td>$annot</td><td style='$revstyle'>$clink1$revtext</td><td>$rbo1</td><td>$annot1</td><td>$clink2</td><td>$rbo2</td><td>$annot2</td></tr>\n";
	}
	print "</table>\n";
	print "<sup>*</sup> Reverse best match and score are also shown, if different, and the entry is shaded <p>";
}
#
# Computes RBO_EXT for uneven lists, eqn. (32) from paper
#
function rbo_score(&$list1, &$list2)
{
	$p = 0.9;
	$k1 = count($list1);
	$k2 = count($list2);
	$kmin = $k1;
	$kmax = $k2;
	if ($kmax < $kmin)
	{
		$kmin = $k2;
		$kmax = $k1;
	}	
	$set1 = array();
	$set2 = array();
	$shared = 0; # running count of shared, =X_d from the paper
	$sum1 = 0;
	$sum2 = 0;
	$pexp = 1;
	$X_s = 0; # of shared at $kmin
	for ($i = 0; $i < $kmax; $i++)
	{
		$gene1 = "";
		$gene2 = "";
		if ($i < $k1)
		{
			$gene1 = $list1[$i];		
			$set1[$gene1] = 1;
		}
		if ($i < $k2)
		{
			$gene2 = $list2[$i];		
			$set2[$gene2] = 1;
		}
		if ($gene1 == "" && $gene2 = "")
		{
			die("both genes null!!");
		}
		if ($gene1 == $gene2)
		{
			# special case because the below logic would increment shared twice
			$shared++;
		}
		else
		{
			if (isset($set2[$gene1]))
			{
				# new gene1 already seen in proj2, hence a new shared
				$shared++;
			}
			if (isset($set1[$gene2]))
			{
				$shared++;
			}
		}
		$pexp *= $p;
		$sum1 += $pexp*($shared/($i + 1));
		if ($i == $kmin - 1)
		{
			$X_s = $shared;
		}
		else if ($i >= $kmin)
		{
			$sum2 += ($pexp*$X_s*($i - $kmin + 1))/($kmin*($i + 1));
		}
	}
	$pfact = ((1-$p)/$p);
	$rbo = $pfact*($sum1 + $sum2);
	
	$rbo += $pexp*( $X_s/$kmin + ($shared - $X_s)/$kmax);
	return $rbo;
}
# get genes in $cid, which are also in $req_list
function get_genelist($cid,&$list,&$req_list)
{
	$s = dbps("select lbl from glist join g2c on glist.id=g2c.gid where g2c.cid=? order by wt desc");
	$s->bind_param("i",$cid);
	$s->bind_result($lbl);
	$s->execute();
	$use_all_genes = (count($req_list) == 0 ? 1 : 0);
	while ($s->fetch())
	{
		$lbl = preg_replace("/\..*/","",$lbl);
		if ($use_all_genes || isset($req_list[$lbl]))
		{
			$list[] = $lbl;
		}
	}
	$s->close();
}


?>

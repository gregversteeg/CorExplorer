<?php
require_once("util.php");

$selected_ids = array();

$Lbltype = getval("lbltype","hugo");
$Use_shared = checkbox_val("shared",0);

$crid1 = getint("crid1",0);
$crid2 = getint("crid2",0);

$shared_checked = ($Use_shared ? " checked='checked' " : "");

?>

<head>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>

<?php
if ($crid1 != 0)
{
	# don't load if not needed
	echo <<<END
<script type="text/javascript" src="http://www.canvasxpress.org/js/canvasXpress.min.js"></script>
<link rel="stylesheet" href="http://www.canvasxpress.org/css/canvasXpress.css" type="text/css"/>
END;
}
?>
</head>

<body>

<?php
if (count($selected_ids) != 0 && count($selected_ids) != 2)
{
	print "<p>Please select two projects<p>";
}
?>

<h3>Compare Factors using Rank-Biased Overlap (RBO) </h3>
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
</table>
</body>

<?php
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
#$groups2 = array(11573);
#$groups1 = array(11846);
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
	$vars = array("\"$pname1\"","\"$pname2\"");   # variables = selected runs
	$varstr = "[".implode(",\n",$vars)."]";
	# Some hoops in case number of groups differ
	$samps = array();	
	$nsamps = max(count($max_rbos1),count($max_rbos2));
	for ($n = 0; $n <= $nsamps; $n++)
	{
		$samps[] = $n;
	}
	$sampstr = "[".implode(",\n",$samps)."]";
	for ($i = count($max_rbos1); $i < $nsamps; $i++)
	{
		$max_rbos1[] = 0;
	}
	for ($i = count($max_rbos2); $i < $nsamps; $i++)
	{
		$max_rbos2[] = 0;
	}
	$datastrs = array();
	arsort($max_rbos1);
	arsort($max_rbos2);
	$datastrs[] = "[".implode(",\n",$max_rbos1)."]";
	$datastrs[] = "[".implode(",\n",$max_rbos2)."]";
	$datastr = "[".implode(",\n",$datastrs)."]";
echo <<<END
    		<canvas  id="canvasId" width="800" height="500" ></canvas>
<script>
var data = {"y": {"vars": $varstr ,
				  "smps": $sampstr ,
				  "data": $datastr 
				 }
			};
var conf = {"graphType": "Line",
			"lineDecoration" : false,
			"smpLabelInterval" : 40,
			"smpTitle" : "Factor",
			"graphOrientation" : "vertical"
			};                 
var cX = new CanvasXpress("canvasId", data, conf);
</script>
END;


	#
	# Print the table
	#
	print "<div style='position:absolute;top:600px;width:900px'>\n";
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
	print "</div>\n";
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

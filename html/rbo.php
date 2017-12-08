<?php
require_once("util.php");

$selected_ids = array();

$Lbltype = getval("lbltype","hugo");

$crid1 = getint("crid1",0);
$crid2 = getint("crid2",0);

?>

<head>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
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
	global $selected_ids, $Lbltype, $crid1, $crid2;
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
	# Using only genes that are in both projects
	#

	#
	# First we have to get the all-project gene lists
	#
	$pinfo1 = array(); $pinfo2 = array();
	load_proj_data($pinfo1,$crid1);
	load_proj_data($pinfo2,$crid2);
	$glid1 = $pinfo1["GLID"];
	$glid2 = $pinfo2["GLID"];
	$all_genes1 = array();
	$all_genes2 = array();
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

	$genes2 = array(); # store proj 2 factor gene lists as we get them
	$results = array();
	foreach ($groups1 as $cid1)
	{
		$genes1 = array();
		$results[$cid1] = array();
		get_genelist($cid1,$genes1,$all_genes2);
		$done = array();
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
					$results[$cid1][$cid2]= $rbo;
				}
			}
		}
	}
	print "<table border=true rules=all cellpadding=3>\n";
	$pname1 = $pinfo1["lbl"];
	$pname2 = $pinfo2["lbl"];
	print "<tr><td colspan=2 align=center><b>$pname1</b></td><td colspan=4 align=center><b>$pname2</b></td></tr>\n";
	print "<tr><td>Factor</td><td>Annotation</td><td>Best Match</td><td>RBO score</td><td>Annotation</td><td>Second Match</td><td>RBO score</td><td>Annotation</td></tr>\n";
	foreach ($results as $cid => $arr)
	{
		arsort($arr);
		$cids = array_keys($arr);
		$cid1 = "";
		$rbo1 = "";
		$clink1 = "";
		if (count($arr) > 0)
		{
			$cid1 = $cids[0];
			$rbo1 = $arr[$cid1];
			$lbl1 = $cid2info[$cid1]["lbl"];
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
		print "<tr><td>$clink</td><td>$annot</td><td>$clink1</td><td>$rbo1</td><td>$annot1</td><td>$clink2</td><td>$rbo2</td><td>$annot2</td></tr>\n";
	}
	print "</table>\n";
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
	while ($s->fetch())
	{
		$lbl = preg_replace("/\..*/","",$lbl);
		if (isset($req_list[$lbl]))
		{
			$list[] = $lbl;
		}
	}
	$s->close();
}


?>

<?php
require_once("db.php");
require_once("util.php");

# convention: inital caps for the page parameters
$FromForm = getint("fromform",0); # tell us if it's initial page load or form submit
$NumGenes = getint("ng",1000);
$MinWt = getnum("mw",0.05);
$MaxClstLvl = getint("maxlvl",2);
$CRID = getint("crid",1);
$CID_sel = getint("cid",0);
$GID_sel = getint("gid",0);
$Goterm = getint("goterm",0);
$Keggterm = getint("keggterm",0);
$Bestinc = checkbox_val("bestinc",1,$FromForm);
$Use_hugo = checkbox_val("use_hugo",1,$FromForm);

$go_enrich_pval = 0.005;
$kegg_enrich_pval = 0.005;

$numSizeBins = 5;		# edge size bins
$level_colors = array("1" => "black", "2" => "#0000cc", "3" => "#9900cc");

$numNodes=0;
$gids_shown = array();
$graph_html = build_graph($numNodes,$NumGenes,$MinWt,$gids_shown);

$go2clst = array();
$kegg2clst = array();
?>

<head>
<style>
    #cy {
        width: 95%;
        height: 95%;
        position: relative;
		border:2px solid gray;
    }
	#foot {
        width: 80%;
        position: absolute;
        top: 95%;
        left: 10%;
	}
</style>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.1.2/cytoscape.js"></script>
</head>
<body>

<form method="get">
<input type="hidden" name="fromform" value="1">
<input type="hidden" name="ft" value="<?php echo $FT ?>">
<input type="hidden" name="fn" value="<?php echo $FN ?>">
<input type="hidden" name="crid" value="<?php print $CRID ?>">
<table >
	<tr>
		<td><b>Graph:</b></td>
		<td> Cluster: <?php print clst_sel("cid",$CID_sel,-1,"--all--") ?> </td>
		<td align="right" style="font-size:1.4em; padding-left:50px;color:#333333" >
			<span id="param_btn" title="Edit parameters" style="cursor:pointer">&nbsp;&#x270e;&nbsp;</span>
			<span id="popout_btn" title="Open in a new page" style="cursor:pointer">&nbsp;&#9654;&nbsp;</span>
		</td>
	</tr>
</table>
<table id="params" style="display:none">
	<tr>
		<td>
			<table cellspacing=10>
				<tr>
					<td  title="<?php print tip_text('genechoose') ?>" >
						Gene: <?php print gene_sel("gid",$GID_sel,$MinWt,$gids_shown) ?> 
					<td >GO enriched (0.005):<?php print go_enrich_sel("goterm",$Goterm,$go2clst) ?>
				</tr>
			</table>
			<table cellspacing=10>
				<tr>
					<td>Num genes: <input name="ng" type="text" size="4" value="<?php print $NumGenes ?>"> 
					</td>
					<td>Link weight:
						 <input name="mw" type="text" size="4" value="<?php print $MinWt ?>">
					</td>
					<td>Max level:
						 <input name="maxlvl" type="text" size="4" value="<?php print $MaxClstLvl ?>">
					</td>
					<td>Best inclusion only:
						 <input name="bestinc" type="checkbox" <?php checked($Bestinc,0) ?>>
					</td>
				</tr>
			</table>
			<table cellspacing=10>
				<tr>
					<td title="<?php print tip_text('hugo_names') ?>">HUGO names:
						 <input name="use_hugo" id="use_hugo_chk" type="checkbox" <?php checked($Use_hugo,0) ?>>
					</td>
					<td >Kegg enriched (0.005):<?php print kegg_enrich_sel("keggterm",$Keggterm,$kegg2clst) ?>
					<td colspan=2 align=left><input type="submit" value="Apply"></td>
				</tr>
			</table>
		</td>
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
#$numNodes=0;
#$graph_html = build_graph($numNodes,$NumGenes,$MinWt);
if ($numNodes == 0)
{
	print "<hr>Empty graph with these settings (try lowering Minimum link weight?)<hr>";
	exit(0);
}
?>
<table width="100%" height="90%">
	<tr>
		<td height="90%">
			<div id="cy"></div>
		</td>
	</tr>
	<tr>
		<td align=left  height="10%">&nbsp; <span id="msg"></span> </td>
	</tr>
</table>
<script>
<?php
	print $graph_html;

	dump_go2clst($go2clst);
	dump_kegg2clst($kegg2clst);
#
# If the graph is in the first frame, then we capture clicks on level one clusters
# and propagate them to the parent window.
#
if ($FN == 1)
{
	echo <<<END
cy.on('click', 'node', function(evt)
{
	var lbl = this.data("lbl");
	if (lbl.indexOf("L1_") == 0) 
	{
		parent.postMessage(this.data("lbl"),"*");
	}
});
END;
}
?>
cy.on('mouseover', 'node', function(evt)
{
	$("#msg").html(this.data('msg'))

	var lbl = this.data("lbl");
	if (lbl.indexOf("L1_") != 0) 
	{ 
		return; 
	}
	
	<?php if ($FN==1) { print '$("body").css( "cursor", "pointer" );';print("\n"); } ?>
});
cy.on('mouseout', 'node', function(evt)
{
	$("#msg").html("")

	var lbl = this.data("lbl");
	if (lbl.indexOf("L1_") != 0) 
	{ 
		return; 
	}

	<?php if ($FN==1) { print '$("body").css( "cursor", "default" );';print("\n"); } ?>
});
cy.on('mouseover', 'edge', function(evt)
{
	$("#msg").html(this.data('msg'))
});
cy.on('mouseout', 'edge', function(evt)
{
	$("#msg").html("")
});
$('#sel_crid').change(function() 
{
	var crid = this.value;
	location.href="/?crid=" + crid;
   	//$('#sel_cid').empty().load( "ajax_fill_cid_sel.php?crid="+crid);
});
$('#use_hugo_chk').change(function() 
{
 	var all = cy.elements("node");
   	for (i = 0; i < all.length; i++) 
	{
       	the_node = all[i];
		if (this.checked)
		{
<?php 
if ($Use_hugo == 0)
{
	# Checkbox was originally unchecked so the hugo name is the alt name
	echo "the_node.addClass('altlbl');\n";
}
else
{
	echo "the_node.removeClass('altlbl');\n";
}
?>
		}
		else
		{
<?php 
if ($Use_hugo == 0)
{
	# Checkbox was originally checked so it's the opposite
	echo "the_node.removeClass('altlbl');\n";
}
else
{
	echo "the_node.addClass('altlbl');\n";
}
?>
		}
   	}
}); 
$(document).ready(function() 
{
	// First set of functions highlights selected cluster or gene node
	var sel_cid = $("#sel_cid");
	var sel_gid = $("#sel_gid");
	var sel_goterm = $("#sel_goterm");
	var sel_keggterm = $("#sel_keggterm");

<?php
# But we don't want to highlight the cluster if only one cluster
# is being shown anyway!
if ($CID_sel == 0)
{
	echo <<<END

	sel_cid.data("prev",sel_cid.val());
	sel_cid.change(function(data)
	{
		var sel = $(this);
		var prev = sel.data("prev");
		var cur = sel.val();
		sel.data("prev",cur);
		node_highlight(prev,"C" + prev,0);
		node_highlight(cur,"C" + cur,1);
		//$("#sel_gid").val("0");  // clst changed, unselect gene
	});
	node_highlight(sel_cid.val(), "C" + sel_cid.val(), 1);
END;
}
# Likewise no need to highlight the clusters with GOs if that's all
# we are showing!
if ($Goterm == 0)
{
	echo <<<END

	sel_goterm.change(function(data)
	{
		var term = $(this).val();
		var all = cy.elements("node");
		// First we have to clear the old cluster highlights, then add these
		for (i = 0; i < all.length; i++) 
		{
			var cynode = all[i];
			var lbl = cynode.data('lbl');
			if (lbl.startsWith("L1_"))
			{
				cynode.removeClass('nodehlt');
			}
		}
		if (!go2clst[term]) 
		{
			return;
		}
		for (var i = 0; i < go2clst[term].length; i++)
		{
			var cid = go2clst[term][i];
			node_highlight(1,"C" + cid,1);
		}
	});
END;
}
# Similar for Keggs
if ($Keggterm == 0)
{
	echo <<<END

	sel_keggterm.change(function(data)
	{
		var term = $(this).val();
		var all = cy.elements("node");
		// First we have to clear the old cluster highlights, then add these
		for (i = 0; i < all.length; i++) 
		{
			var cynode = all[i];
			var lbl = cynode.data('lbl');
			if (lbl.startsWith("L1_"))
			{
				cynode.removeClass('nodehlt');
			}
		}
		if (!kegg2clst[term]) 
		{
			return;
		}
		for (var i = 0; i < kegg2clst[term].length; i++)
		{
			var cid = kegg2clst[term][i];
			node_highlight(1,"C" + cid,1);
		}
	});
END;
}
?>
	sel_gid.data("prev",sel_gid.val());
	sel_gid.change(function(data)
	{
		var sel = $(this);
		var prev = sel.data("prev");
		var cur = sel.val();
		sel.data("prev",cur);
		node_highlight(prev,"G" + prev,0);
		node_highlight(cur,"G" + cur,1);
		//$("#sel_cid").val("0");
	});
	$("#sel_crid").change(function(data)
	{
		$("#sel_cid").val("0");
		$("#sel_gid").val("0");
	});
	node_highlight(sel_gid.val(), "G" + sel_gid.val(), 1);
});
function node_highlight(idnum,idstr,onoff)
{
	//TODO not sure why next line is needed or even the idnum argument
	if (idnum == 0) {return; }
	var cynode = cy.getElementById(idstr);
	if (onoff == 1)
	{
		//cynode.style('background-color','yellow');
		cynode.addClass('nodehlt');
	}	
	else	
	{
		//cynode.style('background-color','black');
		cynode.removeClass('nodehlt');
	}	
}
</script>
</body>


<?php

##############################################################

function build_graph(&$numNodes,$N,$minWt,&$gids_shown)
{
	global $DB, $CRID, $CID_sel, $GID_sel, $Goterm;
	global $Keggterm, $go_enrich_pval, $kegg_enrich_pval, $numSizeBins;
	global $level_colors, $MaxClstLvl, $Bestinc, $Use_hugo;

	$limit_cids_where = "";
	$limit_cids_where2 = "";

	$numNodes = 0;

	if ($CID_sel > 0)
	{
		$limit_cids = array();
		get_connected_clst($limit_cids,$CID_sel,$minWt);
		$limit_cids_where = " and CID in (".implode(",",$limit_cids).")";
		$limit_cids_where2 = " and CID1 in (".implode(",",$limit_cids).")";
		$limit_cids_where2 .= " and CID2 in (".implode(",",$limit_cids).")";
	}
	$gene_cids_where = "";
	$gene_cids_where2 = "";
	if ($GID_sel > 0)
	{
		# A gene was selected. 
		# Get the clusters for this gene, and then get the higher-layer
		# connected clusters...a bit awkward
		#
		$gene_cids = array();
		$gene_wts = array();
		get_gene_cids($gene_cids,$gene_wts,$GID_sel,$CRID,$minWt);	
		$uniqe_cids = array();
		foreach ($gene_cids as $cid)
		{
			$unique_cids[$cid] = 1;
		}
		foreach ($gene_cids as $cid)
		{
			$connected_cids = array();
			get_connected_clst($connected_cids,$cid,$minWt);
			foreach ($connected_cids as $new_cid)
			{
				$unique_cids[$new_cid] = 1;
			}
		}
		$gene_cids_where = " and CID in (".implode(",",array_keys($unique_cids)).")";
		$gene_cids_where2 = " and CID1 in (".implode(",",array_keys($unique_cids)).")";
		$gene_cids_where2 .= " and CID2 in (".implode(",",array_keys($unique_cids)).")";
	}

	$go_cids = array();
	$go_where = "";
	$go_genes = array();
	if ($Goterm != 0)
	{
		# get the clusters enriched for this go, and also the genes which have it
		$st = $DB->prepare("select CID from clst2go join clst on clst.ID=clst2go.CID ".
			" where term=? and pval <= ? and CRID=?");
		$st->bind_param("idi",$Goterm,$go_enrich_pval,$CRID);
		$st->bind_result($cid);
		$st->execute();
		while ($st->fetch())
		{
			$go_cids[] = $cid;
		}
		$st->close();
		if (count($go_cids) > 0)
		{
			$go_where = " and CID in (".implode(",",$go_cids).")";
		}
		$st = $DB->prepare("select g2g.gid from g2g join g2c on g2c.gid=g2g.gid ".
			" where g2g.term=? and g2c.crid=?");
		$st->bind_param("ii",$Goterm,$CRID);
		$st->bind_result($gid);
		$st->execute();
		while ($st->fetch())
		{
			$go_genes[$gid] = 1;
		}
		$st->close();
		
	}

	$kegg_cids = array();
	$kegg_where = "";
	if ($Keggterm != 0)
	{
		$st = $DB->prepare("select CID from clst2kegg join clst on clst.ID=clst2kegg.CID ".
			" where term=? and pval <= ? and CRID=?");
		$st->bind_param("idi",$Keggterm,$kegg_enrich_pval,$CRID);
		$st->bind_result($cid);
		$st->execute();
		while ($st->fetch())
		{
			$kegg_cids[] = $cid;
		}
		$st->close();
		if (count($kegg_cids) > 0)
		{
			$kegg_where = " and CID in (".implode(",",$kegg_cids).")";
		}
	}

	#
	# Get the level 1 data
	# Add the nodes and save the links to put after (is it necessary?)
	#

	$nodes = array();
	$links = array();

	$sql = "select GID, CID, glist.lbl as lbl,glist.hugo as hugo, glist.descr as descr, mi,wt,clst.lbl as cnum ".
		" from g2c join glist on glist.ID=g2c.GID ";
	$sql .= " join clst on clst.ID = g2c.CID ";
	$sql .= " where g2c.CRID=? and wt >= ? $go_where $kegg_where $limit_cids_where $gene_cids_where ";
	$sql .=	" order by wt desc limit $N"; 
	$st = $DB->prepare($sql);
	$st->bind_param("id",$CRID,$minWt);
	$st->bind_result($GID,$CID,$gene_name,$hugo_name,$gene_desc,$mi,$wt,$cnum);
	$st->execute();
	$elements = array();
	$CIDlist = array(); # for building the next-level query
	$gene_node_data = array();
	$gid2names = array();
	$gid2hugo = array();
	$gid2desc = array();
	$minwt = $minWt;  # FIXME
	$maxwt = -1;
	$color = $level_colors["1"];
	$gid_seen = array();    
	while ($st->fetch())
	{
		if ($Bestinc && isset($gid_seen[$GID]))
		{
			continue;
		}
		$gid_seen[$GID] = 1;

		$CIDtag = "C$CID";
		$CIDlbl = "L1_$cnum";
		$GIDtag = "G$GID";

		if ($wt > 0 && ($minwt == -1 || $wt < $minwt))
		{
			$minwt = $wt;
		}
		if ($maxwt == -1 || $wt > $maxwt)
		{
			$maxwt = $wt;
		}

		if (!isset($nodes[$CIDtag]))
		{
			# We're putting a "hugo" name in here as one way (maybe not the best)
			# to make the gene name switching not mess up the cluster names
			$elements[] = "{data: {id: '$CIDtag', size:'25px', lbl:'$CIDlbl', hugo:'$CIDlbl', msg:'cluster:$CIDlbl', 
					link:'/ppi.php?CRID=$CRID&CID=$CID&corex_score=$minWt', color:'$color'}}";
					#link:'/ppi/run1/".($CID-1).".stringNetwork.png'}}";
			$nodes[$CIDtag] = 1;
			$CIDlist[] = $CID;
		}	

		# we have to collect all the cluster data before making the gene
		# node entry since the message will list the clusters the gene is in

		$gid2names[$GID] = $gene_name;
		$gid2hugo[$GID] = $hugo_name;
		$gid2desc[$GID] = $gene_desc;
		$gene_node_data[$GID][] = array("cnum" => $cnum, "wt" => $wt, "mi" => $mi);

		$links[] = array("src" => "$GIDtag", "targ" => "$CIDtag", "wt" => $wt, "mi" => $mi);
	}
	$st->close();
	foreach ($gene_node_data as $GID => $darray)
	{
		$gname = $gid2names[$GID];
		$hugo = $gid2hugo[$GID];
		$desc = $gid2desc[$GID];
		$info = array();
		foreach ($darray as $data)
		{
			$cnum 	= $data["cnum"];	
			$wt 	= $data["wt"];	
			$mi 	= $data["mi"];	
			$info[] = "$cnum (Wt=$wt, MI=$mi)";
		}
		$GIDtag = "G$GID";
		$msg = ($gname == $hugo ? "gene:$gname" : "gene:$gname ($hugo)");
		if ($desc != "") 
		{
			$msg .= " ".json_encode($desc, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_APOS);
		}
		$nclst = count($info);
		$msg .= "<br>contained in $nclst clusters: ".implode("; ",$info);
		#$lbl = ($Use_hugo == 0 ? $gname : $hugo);
		#$lbl2 = ($Use_hugo == 1 ? $gname : $hugo);
		$clr = (isset($go_genes[$GID]) ? "yellow" : "red");
		$elements[] = "{data: {id: '$GIDtag', size:'15px', lbl:'$gname', 
						hugo:'$hugo', msg:'$msg', color:'$clr'}}";
		$nodes[$GIDtag] = 1;
		$gids_shown[$GID] = 1;
	}

	#
	# Get the higher level data
	# First we need the number of levels and the cluster numbers
	# NOTE LEVELS IN DB START AT 0 WHILE DISPLAY STARTS AT 1!!!

	$st = dbps("select max(lvl) as maxlvl from clst where CRID=?");
	$st->bind_param("i",$CRID);
	$st->bind_result($maxlvl);
	$st->execute();
	$st->fetch();
	$st->close();
	$maxLvl = min($maxlvl + 1, $MaxClstLvl);	

	$cid2num = array();
	$st = $DB->prepare("select ID, lbl from clst where CRID=?");
	$st->bind_param("i",$CRID);
	$st->bind_result($id,$lbl);
	$st->execute();
	while ($st->fetch())
	{
		$cid2num[$id] = $lbl;
	}	
	$st->close();
	
	$lvl = 2;  # this is the level we are linking TO
	while ($lvl <= $maxLvl)
	{
		$size = 15 + 15*$lvl;
		$size .= "px";
		$color = $level_colors[$lvl];

		$cur_CID_where = "";
		if (count($CIDlist) > 0)
		{
			$cur_CIDs = "(".implode(",",$CIDlist).")";
			$cur_CID_where = " and CID1 in $cur_CIDs ";
		}
		$CIDlist = array();

		$cid1seen = array();

		$st = $DB->prepare("select CID1, CID2,wt,mi from c2c where wt >= ? and CRID=? $cur_CID_where ".
					" $limit_cids_where2 $gene_cids_where2 order by wt desc");
		$st->bind_param("di",$minWt,$CRID);
		$st->bind_result($CID1,$CID2,$wt,$mi);
		$st->execute();
		while ($st->fetch())
		{
			if ($Bestinc && isset($cid1seen[$CID1]))
			{
				continue;
			}
			$cid1seen[$CID1] = 1;

			$cnum1 = $cid2num[$CID1];
			$cnum2 = $cid2num[$CID2];

			$CID2tag = "C$CID2";
			$CID1tag = "C$CID1";
			$CID2lbl = "L$lvl"."_$cnum2";
			$CID1lbl = "L".($lvl-1)."_$cnum1";

			if ($wt > 0 && ($minwt == -1 || $wt < $minwt))
			{
				$minwt = $wt;
			}
			if ($maxwt == -1 || $wt > $maxwt)
			{
				$maxwt = $wt;
			}

			if (!isset($nodes[$CID2tag]))
			{
				# We're putting a "hugo" name in here as one way (maybe not the best)
				# to make the gene name switching not mess up the cluster names
				$elements[] = "{data: {id: '$CID2tag', size:'$size', lbl:'$CID2lbl', hugo:'$CID2lbl',
					link:'', msg:'cluster:$CID2lbl', color:'$color'}}";
				$nodes[$CID2tag] = 1;
				$CIDlist[] = $CID2; 
			}
			$links[] = array("src" => "$CID1tag", "targ" => "$CID2tag", "wt" => "$wt", "mi" => "$mi");
		}
		$st->close();
		$lvl++;
	}
	
	#$wt_range = log($maxwt/$minwt);
	$wt_range = $maxwt - $minwt;

	foreach ($links as $data)
	{
		$src =  $data["src"];
		$targ =  $data["targ"];
		$wt =  $data["wt"];
		$mi =  $data["mi"];
		$id = $src."_".$targ;

		#$diffwt = log($wt/$minwt); 
		$diffwt = $wt - $minwt; 
		$sizebin = min($numSizeBins,1 + floor($numSizeBins*$diffwt/$wt_range));
		$opacity = 0.2 + (0.8/$numSizeBins)*$sizebin;
		$width = (2*$sizebin)."px";
		$elements[] = "{data: { id:'$id', source: '$src', target: '$targ', ".
					" msg: 'weight:$wt,MI:$mi', width: '$width', opacity: '$opacity'}}";
	}
	$numNodes = count($nodes);
	$html = <<<END
var cy = cytoscape({
  container: document.getElementById('cy'),
//autoungrabify: 'true',
 wheelSensitivity: .1,
END;
	$html .= "elements: [".implode(",\n",$elements)."],\n";
	$lbltype = ($Use_hugo == 0 ? "lbl" : "hugo");
	$altlbltype = ($Use_hugo == 1 ? "lbl" : "hugo");
	$html .= <<<END
style:[ 
    {
      selector: 'node',
      style: {
        'background-color': 'data(color)',
        'label': 'data($lbltype)',
		'font-size' : '25px',
		'width' : 'data(size)',
		'height' : 'data(size)'
      }
    },
	{
		selector: '.altlbl',
		style: {
        	'label': 'data($altlbltype)'
		}
	},
    {
      selector: 'edge',
      style: {
        'width': 'data(width)',
		'opacity' : 'data(opacity)',
        'line-color': 'green',
        'target-arrow-color': 'blue',
        'target-arrow-shape': 'triangle'
      }
    },
	{
		selector: '.nodehlt',
		style: {
			'text-background-color' : 'yellow',
			'text-background-opacity' : '0.5'
		}
	}		
	
  ],
layout:{ name: 'cose'}
});
END;
	return $html;
}

function go_enrich_sel($name, $sel,&$go2clst)
{
	global $CRID, $DB, $go_enrich_pval, $Goterm;

	$opts = array();
	$selected = ($Goterm == 0 ? " selected " : "");
	$opts[] = "<option value='0' $selected>none</option>";
	$terms_seen = array();
	$st = $DB->prepare("select gos.term as term, gos.descr as descr,clst.id as cid ".
				" from clst2go join gos on gos.term=clst2go.term ".
				" join clst on clst.ID=clst2go.CID ".
				" where clst.CRID=? and clst2go.pval <= ? ".
				" and gos.CRID=? order by term asc, clst.ID asc ");
	$st->bind_param("idi",$CRID,$go_enrich_pval,$CRID);
	$st->bind_result($term,$descr,$cid);
	$st->execute();
	while ($st->fetch())
	{
		if (!isset($go2clst[$term]))
		{
			$go2clst[$term] = array();
		}
		$go2clst[$term][$cid] = 1;

		if (strlen($descr) > 25)
		{
			$descr = substr($descr,0,22)."...";
		}
		if (isset($terms_seen[$term]))
		{
			continue;
		}
		$terms_seen[$term] = 1;
		$selected = ($term == $Goterm ? " selected " : "");
		$goname = go_name($term);
		$opts[] = "<option value='$term' $selected>$goname $descr</option>";
	}
	$st->close();
	return "<select name='$name' id='sel_$name'>\n".implode("\n",$opts)."\s</select>\n";
}
function kegg_enrich_sel($name, $sel,&$kegg2clst)
{
	global $CRID, $DB, $kegg_enrich_pval, $Keggterm;

	$opts = array();
	$selected = ($Keggterm == 0 ? " selected " : "");
	$opts[] = "<option value='0' $selected>none</option>";
	$terms_seen = array();
	$st = $DB->prepare("select kegg.term as term, kegg.descr as descr, clst.id as cid ".
				"  from clst2kegg join kegg on kegg.term=clst2kegg.term ".
				" join clst on clst.ID=clst2kegg.CID where kegg.CRID=? and ".
				"  clst.CRID=? and clst2kegg.pval <= ? order by term asc ");
	$st->bind_param("iid",$CRID,$CRID,$kegg_enrich_pval);
	$st->bind_result($term,$descr,$cid);
	$st->execute();
	while ($st->fetch())
	{
		if (strlen($descr) > 25)
		{
			$descr = substr($descr,0,22)."...";
		}
		if (!isset($kegg2clst[$term]))
		{
			$kegg2clst[$term] = array();
		}
		$kegg2clst[$term][$cid] = 1;
		if (isset($terms_seen[$term]))
		{
			continue;
		}
		$terms_seen[$term] = 1;
		$selected = ($term == $Keggterm ? " selected " : "");
		$keggname = kegg_name($term);
		$opts[] = "<option value='$term' $selected>$keggname $descr</option>";
	}
	$st->close();
	return "<select name='$name' id='sel_$name'>\n".implode("\n",$opts)."\s</select>\n";
}
function get_clst_level($cid)
{
	global $DB;
	$st = $DB->prepare("select lvl from clst where ID=$cid");
	$st->bind_param("i",$cid);
	$st->bind_result($lvl);
	$st->execute();
	$st->fetch();
	$st->close();
	return $lvl;
}
#
# Given a cluster, find all subclusters (uses the link weight param). 
#
function get_connected_clst(&$cids, $cid, $wt)
{
	global $DB;
	$cids[] = $cid;
	$maxlvl = get_clst_level($cid);
	$cur_cids = array($cid);
	for ($lvl = $maxlvl; $lvl > 0; $lvl--)
	{
		$cids_where = " and CID2 in (".implode(",",$cur_cids).") ";
		$st = $DB->prepare("select CID1 from c2c where wt >= ? $cids_where ");
		$st->bind_param("d",$wt);
		$st->bind_result($cid1);
		$st->execute();
		$cur_cids = array();
		while ($st->fetch())
		{
			$cur_cids[] = $cid1;
			$cids[] = $cid1;
		}
		$st->close();
	}
}
function gene_sel($name,$sel_GID,$minwt,&$gids_shown)
{
	global $CRID, $DB;

	$g2c = array();
	$gids = array();
	$st = $DB->prepare("select glist.ID as GID,glist.lbl as gname, clst.ID as CID,clst.lbl as cnum ".
		"  from glist join g2c on g2c.GID=glist.ID ".
		" join clst on clst.ID=g2c.CID where g2c.CRID=? and g2c.wt >= ? order by gname asc");
	$st->bind_param("id",$CRID,$minwt);
	$st->bind_result($GID,$gname,$CID,$cnum);
	$st->execute();
	while ($st->fetch())
	{
		$g2c[$gname][] = $cnum;
		$gids[$gname] = $GID;
	}
	$st->close();
	$opts = array();
	$selected = ($sel_GID == 0 ? " selected " : "");
	$opts[] = "<option value=0 $selected>all genes</option>\n";
	foreach ($g2c as $gname => $cnums)
	{
		$GID = $gids[$gname];
		if (count($cnums) <= 5)
		{
			$cnumstr = implode(",",$cnums);
		}
		else
		{
			$cnumstr = count($cnums)."  clusters";
		}
		$selected = ($sel_GID == $GID ? " selected " : "");
		#$starred = (isset($gids_shown[$GID]) ? "" : "*");
		$optstyle = (isset($gids_shown[$GID]) ? " style='background-color:yellow' " : "");
		$opts[] = "<option value='$GID' $selected $optstyle>$gname ($cnumstr)</option>";
	}	
	$html = "<select name='$name' id='sel_$name'>\n";
	$html .= implode("\n",$opts)."\n";
	$html .= "</select>\n";
	return $html;	
		
}
# TODO do we want bestinc to act here
function get_gene_cids(&$cids,&$gene_wts,$GID,$CRID,$minwt,$Bestinc=0)
{
	global $DB;
	$bestinc_limit = ($Bestinc ? " limit 1 " : "");
	$st = $DB->prepare("select g2c.CID,g2c.wt,g2c.mi,clst.lbl as cnum from g2c join clst on clst.ID=g2c.CID ".
					" where g2c.GID=? and g2c.CRID=? and g2c.wt >= ? ".
					" order by g2c.wt desc $bestinc_limit");
	$st->bind_param("iid",$GID,$CRID,$minwt);
	$st->bind_result($CID,$wt,$mi,$cnum);
	$st->execute();
	while ($st->fetch())
	{
		$cids[] = $CID;	
		$gene_wts[$cnum] = array("wt" => $wt, "mi" => $mi);
	}
	$st->close();
}
function dump_go2clst(&$g2c)
{
	# note, must already be inside a script block!
	print "var go2clst = new Array();\n";
	foreach ($g2c as $term => $carr)
	{
		$astr = implode("','",array_keys($carr));
		print "go2clst[$term] = new Array('$astr');\n";
	}

}
function dump_kegg2clst(&$k2c)
{
	# note, must already be inside a script block!
	print "var kegg2clst = new Array();\n";
	foreach ($k2c as $term => $carr)
	{
		$astr = implode("','",array_keys($carr));
		print "kegg2clst[$term] = new Array('$astr');\n";
	}

}

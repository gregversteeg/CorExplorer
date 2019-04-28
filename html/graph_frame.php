<?php
require_once("util.php");
ini_set('memory_limit', '1G');

# convention: inital caps for the page parameters
$FromForm = getint("fromform",0); # tell us if it's initial page load or form submit
$NumGenes = 10000; #getint("ng",1000);
$MaxClstLvl = getint("maxlvl",2);
$CRID = getint("crid",1);
$CID_sel = getint("cid",0);
$GID_sel = getint("gid",0);
$Goterm = getint("goterm",0);
$Keggterm = getint("keggterm",0);
$Bestinc = checkbox_val("bestinc",1,$FromForm);
$Use_hugo = checkbox_val("use_hugo",1,$FromForm);

$pdata = array();
load_proj_data($pdata,$CRID);

$MinWt = getnum("mw",$pdata["def_wt"]);

if (!read_access($CRID))
{
	die("access denied");
}

$go_enrich_pval = 0.005;
$kegg_enrich_pval = 0.005;

$numSizeBins = 5;		# edge size bins
$level_colors = array("1" => "black", "2" => "#0000cc", "3" => "#9900cc");

$go2clst = array();
$kegg2clst = array();

?>

<head>
<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
<style>
    #cy {
        width: 100%;
        height: 100%;
        position: relative;
		/*border:1px solid #d5d5d5;*/
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
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
</head>
<body>

<!--form method="get">
<input type="hidden" name="fromform" value="1">
<input type="hidden" name="ft" value="<?php echo $FT ?>">
<input type="hidden" name="fn" value="<?php echo $FN ?>">
<input type="hidden" name="crid" value="<?php print $CRID ?>"-->
<table width="100%" cellspacing=0 cellpadding=0>
	<tr>
		<td valign="top" align="left">
		<table cellspacing=0 cellpadding=0 >
			<tr>
				<td align="left"> Factor: <?php print clst_sel("cid",$CID_sel,-1,"--all--") ?> </td>
				<td align="left"  title="<?php print tip_text('genechoose') ?>" style="padding-left:25px">
						Gene: <?php print gene_sel("gid",$GID_sel,$MinWt,$gids_shown) ?> 
				</td>
				<td>&nbsp;
				</td>
			</tr>
			<tr >
				<td align="left" valign="top" style="padding-top:3px">
					<table cellspacing=0 cellpadding=0>
						<tr>
							<td >Kegg enriched:</td>
						</tr>
						<tr>
							<td><?php print kegg_enrich_sel("keggterm",$Keggterm,$kegg2clst) ?></td>
						</tr>
					</table>
				</td>
				<td align="left"  valign="top" style="padding-left:25px;padding-top:3px;">
					<table cellspacing=0 cellpadding=0>
						<tr>
							<td >GO enriched:</td>
						</tr>
						<tr>
							<td ><?php print go_enrich_sel("goterm",$Goterm,$go2clst) ?>
						</tr>
					</table>
				</td>
				<td>&nbsp;
				</td>
			</tr>
			<tr>
				<td colspan=3 align="left" style="padding-top:6px;">
					<table cellspacing=0 cellpadding=0 width="100%">
						<tr>
							<td valign="bottom" >Link Weight: </td>
							<td  valign="bottom" style="width:150px;padding-left:10px" ><div id="mw_slider"></div> </td>
							<td valign="bottom"  style="padding-left:3px">
								 <input name="mw" id="txt_mw" type="text" size="3" value="<?php print $MinWt ?>">
							</td>
							<td valign="bottom"  style="padding-left:10px;" title="<?php print tip_text('hugo_names') ?>">HUGO names:
								 <input name="use_hugo" id="use_hugo_chk" type="checkbox" <?php checked($Use_hugo) ?>>
							</td>
							<td  valign="bottom" style="padding-left:10px;" >Best inclusion only:
								 <input name="bestinc" id="chk_bestinc" type="checkbox" <?php checked($Bestinc) ?>>
							</td>
							<td  valign="bottom" style="padding-left:10px"><a href="" onclick="do_reset();return false;">reset</a></td>
						</tr>
					</table>
				</td>
			</tr>
			<!--tr>
				<td colspan=3 align="right" valign="top" style="padding-top:6px" >
					<table cellspacing=0 cellpadding=0 >
						<tr>
							<td ><input type="submit" value="Apply"></td>
							<td style="padding-left:10px" ><input type="submit" 
								onclick="window.location.href='<?php echo $Frame_reset_url ?>';return false"
								value="Reset">
							</td>
						</tr>
					</table>
				</td>
			</tr-->
		</table>
		</td>
<?php
$graph_disabled = ""; #($CID_sel==0 ? "" : " disabled='true' ");
if (write_access($CRID))
{
	echo <<<END
		<td valign="top">
			<table>
				<tr>
					<td><button type="button" id="save_graph_btn" onclick="save_graph();return false" $graph_disabled>
							Save Graph</button>
					</td>
				</tr>
				<tr>
					<td><button type="button" id="clear_graph_btn" onclick="clear_graph();return false" $graph_disabled>
							Clear Graph</button>
					</td>
				</tr>
				<tr>
					<td><button type="button" onclick="save_wt();return false">Save Link Weight</button></td>
				</tr>
			</table>
		</td>
END;
}

?>
		<td valign="top" align="right" style="font-size:1.4em; padding-right:50px;color:#333333" >
			<span id="popout_btn" title="Open in a new page" style="cursor:pointer">&nbsp;&#9654;&nbsp;</span>
			<!--span id="param_btn" title="Edit parameters" style="cursor:pointer">&nbsp;&#x270e;&nbsp;</span-->
		</td>
	</tr>
</table>
<!--/form-->
<table width="100%" height="100%" cellspacing= 0 cellpadding=0 style="margin-top:10px">
	<tr>
		<td height="93%" valign="top" style="border:1px solid #d5d5d5;">
			<span id="loading" style="font-size:17px">Graph is loading...  </span>
			<div id="cy"></div>
		</td>
	</tr>
	<tr>
		<td align=left  valign="top" height="7%" style="padding-top:5px">&nbsp; <span id="msg"></span> </td>
	</tr>
</table>
<script>
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

$numNodes=0;
$gids_shown = array();
$clstContent = array();
$actualMinWt = $MinWt;
$actualMaxWt = 1;
$graph_html = build_graph($numNodes,$NumGenes,$MinWt,$gids_shown,$clstContent,$actualMinWt,$actualMaxWt);

#$numNodes=0;
#$graph_html = build_graph($numNodes,$NumGenes,$MinWt);
if ($numNodes == 0)
{
	print "<hr>Empty graph with these settings (try lowering Minimum link weight?)<hr>";
	exit(0);
}
?>
<script>
<?php
	print $graph_html;

	dump_go2clst($go2clst);
	dump_kegg2clst($kegg2clst);
	dump_clst_content($clstContent);
?>
cy.on('mouseover', 'node', function(evt)
{
	$("#msg").html(this.data('msg'))
	$("body").css( "cursor", "pointer" );
	this.addClass('nodeshowtext');
});
cy.on('mouseout', 'node', function(evt)
{
	$("#msg").html("")
	$("body").css( "cursor", "default" );
	this.removeClass('nodeshowtext');
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
});
$('#chk_bestinc').change(function() 
{
	show_hide_nodes_edges();
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
var go2gene = []; // map GOs to genes, done through ajax

var slider_offset = .001;
function slider_from_log(log)
{
	return (Math.pow(10,log) - slider_offset);	
}
function slider_to_log(val)
{
	return Math.log10(slider_offset + val);
}
var min_wt_init = <?php echo $MinWt ?>;
var slider_init = slider_to_log(min_wt_init);
$(document).ready(function() 
{
	// 
	// These functions highlight and zoom to selected clusters. In the case of GO or Kegg,
	// they blank out the clusters that don't have those annotations. 
	// 
	var sel_cid = $("#sel_cid");
	var sel_gid = $("#sel_gid");
	var sel_goterm = $("#sel_goterm");
	var sel_keggterm = $("#sel_keggterm");

	var slider_min = slider_to_log(0);
	var slider_max = slider_to_log(<?php echo $actualMaxWt ?>);
	var slider_step = .01*(slider_max - slider_min);
	
	$( "#mw_slider" ).slider({
		min:slider_min, 
		max:slider_max, 
		step:slider_step, 
		value:slider_init,
		slide: function( event, ui ) {
			var val = slider_from_log(ui.value);
			$("#txt_mw").val(val.toFixed(4));
			show_hide_nodes_edges();
	   }
	});

	sel_cid.data("prev",sel_cid.val());
	sel_cid.change(function(data)
	{
		var sel = $(this);
		var prev = sel.data("prev");
		var cur = sel.val();
		sel.data("prev",cur);
		node_highlight(prev,"C" + prev,0);
		node_highlight(cur,"C" + cur,1);
		node_zoom(cur,"C" + cur);
		//$("#sel_gid").val("0");  // clst changed, unselect gene
	});

<?php 
# Likewise no need to highlight the clusters with GOs if that's all
# we are showing!
if ($Goterm == 0)
{
	echo <<<END

	sel_goterm.change(function(data)
	{
		var term = $(this).val();
		if (term > 0 && !go2clst[term]) 
		{
			alert("err:bad GO term " + term);
			return;
		}

		clear_gene_highlight();
		if (term != 0)
		{
			if (!go2gene[term])
			{
				ajax_get_gos(term);
			}
			else
			{
				highlight_go_genes(term);
			}	
		}

		// Now do the show/hide of clusters
		// first get the kegg enriched cids, if any, so we
		// can do intersection
		var keggterm = sel_keggterm.val();
		kegg_cids_to_keep = new Array();
		if (keggterm > 0)
		{
			for (i = 0; i < kegg2clst[keggterm].length; i++)
			{
				cid = kegg2clst[keggterm][i];
				kegg_cids_to_keep[cid] = 1;
			}
		}

		var all = cy.elements("node");
		// First we have to clear the old cluster and gene highlights, then add new
		for (i = 0; i < all.length; i++) 
		{
			cynode = all[i];
			if (cynode.data('lbl').startsWith("L1_"))
			{
				cynode.removeClass('nodehlt');
			}
		}
		show_all_nodes();
		if (term == 0)
		{
			if (keggterm > 0) 
			{
				hide_nodes_by_cluster(kegg_cids_to_keep);	
			}
		} 
		else
		{
			cids_to_keep = new Array();
			for (var i = 0; i < go2clst[term].length; i++)
			{
				var cid = go2clst[term][i];
				node_highlight(1,"C" + cid,1);
				if (keggterm == 0 || kegg_cids_to_keep[cid])
				{
					cids_to_keep[cid] = 1;
				}
			}
			hide_nodes_by_cluster(cids_to_keep);
		}

	});

END;
}
else # GO term was specified -- but we can still respond to selection change by highlighting genes
{
	echo <<<END

	sel_goterm.change(function(data)
	{
		var term = $(this).val();
		clear_gene_highlight();
		if (term != 0)
		{
			if (!go2gene[term])
			{
				ajax_get_gos(term);
			}
			else
			{
				highlight_go_genes(term);
			}	
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
		if (term > 0 && !kegg2clst[term]) 
		{
			alert("err:bad Kegg term " + term);
			return;
		}

		var goterm = sel_goterm.val();
		go_cids_to_keep = new Array();
		if (goterm > 0)
		{
			for (i = 0; i < go2clst[goterm].length; i++)
			{
				cid = go2clst[goterm][i];
				go_cids_to_keep[cid] = 1;
			}
		}

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
		show_all_nodes();
		if (term == 0)
		{
			if (goterm > 0)
			{
				hide_nodes_by_cluster(go_cids_to_keep);
			}
		} 
		else
		{
			cids_to_keep = new Array();
			for (var i = 0; i < kegg2clst[term].length; i++)
			{
				var cid = kegg2clst[term][i];
				node_highlight(1,"C" + cid,1);
				if (goterm == 0 || go_cids_to_keep[cid])
				{
					cids_to_keep[cid] = 1;
				}
			}
			hide_nodes_by_cluster(cids_to_keep);
		}
	});
END;
}
?>
	$("#txt_mw").change(function(data)
	{
		var txtval = slider_to_log($(this).val());
		var sliderval = $("#mw_slider").slider("option","value");
		if (sliderval != txtval)
		{
			$("#mw_slider").slider("value", txtval);
			show_hide_nodes_edges(txtval);
		}
	}); 
	sel_gid.data("prev",sel_gid.val());
	sel_gid.change(function(data)
	{
		var sel = $(this);
		var prev = sel.data("prev");
		var cur = sel.val();
		var cur_idstr = "G" + cur;
		sel.data("prev",cur);
		node_highlight(prev,"G" + prev,0);
		node_highlight(cur,"G" + cur,1);
		gene_zoom(cur,cur_idstr);
	});
	$("#sel_crid").change(function(data)
	{
		$("#sel_cid").val("0");
		$("#sel_gid").val("0");
	});
	node_highlight(sel_gid.val(), "G" + sel_gid.val(), 1);

	add_drag_listeners();

});  // end of document.ready

function do_reset()
{
	$("#txt_mw").val(min_wt_init);
	$("#mw_slider").slider("value",slider_init);
	$("#chk_bestinc").prop("checked",true);
	$("#sel_cid").val("0");
	$("#sel_keggterm").val("0");
	$("#sel_goterm").val("0");
	$("#sel_gid").val("0");
	show_hide_nodes_edges();
	cy.fit(); 
}
function set_min_wt(wt)
{
	wt_round = wt.toFixed(4);
	$("#txt_mw").val(wt_round);
	$("#mw_slider").slider("value",slider_to_log(wt_round));
}
function show_hide_nodes_edges()
{
	//
	// First collect the selector settings 
	//
	var bestinc = $('#chk_bestinc').prop("checked"); 
	var keggterm = $("#sel_keggterm").val();
	var goterm = $("#sel_goterm").val();
	var minwt = $("#txt_mw").val();

	//
	// If there is a GO or Kegg selected, then we need the
	// relevant set of clusters.
	//
	go_cids_to_keep = new Array();
	kegg_cids_to_keep = new Array();
	if (goterm > 0)
	{
		for (i = 0; i < go2clst[goterm].length; i++)
		{
			cid = go2clst[goterm][i];
			go_cids_to_keep[cid] = 1;
		}
	}
	if (keggterm > 0)
	{
		for (i = 0; i < kegg2clst[keggterm].length; i++)
		{
			cid = kegg2clst[keggterm][i];
			kegg_cids_to_keep[cid] = 1;
		}
	}
	//
	// Go through the edges first because Cytoscape automatically hides edges where
	// a node is hidden...so we won't have nodeless edges becoming visible temporarily.
	// For the same reason, we only have to use weight and linknum to decide the edges;
	// we don't have to worry whether their cluster is being shown. 
	//
	var all_edges = cy.edges();
   	for (i = 0; i < all_edges.length; i++) 
	{
       	var edge = all_edges[i];
		var lnum = edge.data("lnum");
		var wt = parseFloat(edge.data("wt"));
		if (lnum > 1)
		{
			if (bestinc)
			{
				edge.addClass("nodehide");
			}
			else
			{
				if (wt >= minwt)
				{	
					edge.removeClass("nodehide");
				}
				else
				{	
					edge.addClass("nodehide");
				}
			}
		}
		else
		{
			if (wt >= minwt)
			{	
				edge.removeClass("nodehide");
			}
			else
			{	
				edge.addClass("nodehide");
			}
		}
	}
	//
	// Now do the nodes, where we do need to consider the GO/Kegg clusters.
	//
	var all_nodes = cy.nodes();
	for (j = 0; j < all_nodes.length; j++) 
	{
		var node = all_nodes[j];
		if (node.data('id').startsWith("G"))
		{
			// Gene node
			var wt = node.data("wt");
			var cid = node.data("cid");
			if (goterm > 0 && !go_cids_to_keep[cid])
			{
				node.addClass('nodehide');
				continue;
			}
			if (keggterm > 0 && !kegg_cids_to_keep[cid])
			{
				node.addClass('nodehide');
				continue;
			}
			if (wt >= minwt)
			{
				node.removeClass('nodehide');
			}
			else
			{
				node.addClass('nodehide');
			}
		}
		else if (node.data('id').startsWith("C"))
		{
			// Cluster node
			lvl = node.data('lvl');	
			cid = node.data('cid');
			if (lvl <= 1)
			{
				keep = 1;
				if (goterm > 0 && !go_cids_to_keep[cid])
				{
					keep = 0;
				} 
				if (keggterm > 0 && !kegg_cids_to_keep[cid])
				{
					keep = 0;
				} 
				if (keep)
				{
					node.removeClass('nodehide');
				}	
				else	
				{
					node.addClass('nodehide');
				}	
			}
			else
			{
				// higher level node; see if it contains any non-hidden node
				if (clst_cont[cid])
				{
					keep = 0;
					for (k = 0; k < clst_cont[cid].length; k++)
					{
						cid2 = clst_cont[cid][k];
						keep2 = 1;
						if (goterm > 0 && !go_cids_to_keep[cid2])
						{
							keep2 = 0;
						} 
						if (keggterm > 0 && !kegg_cids_to_keep[cid2])
						{
							keep2 = 0;
						} 
						if (keep2)
						{
							keep = 1;
							break;
						}	
					}
					if (keep)
					{
						cynode.removeClass('nodehide');
					} 
					else	
					{
						cynode.addClass('nodehide');
					} 
				
				}
				else
				{
					console.log("err:cid=" + cid + " has no content");
				}
			}
		}
		else
		{
			console.log("Extra node");
			console.log(JSON.stringify(node.data));
		}
	}
}
function show_hide_edges(minwt)
{
	var bestinc = $('#chk_bestinc').prop("checked"); 
	var all_edges = cy.edges();
   	for (i = 0; i < all_edges.length; i++) 
	{
       	var edge = all_edges[i];
		var lnum = edge.data("lnum");
		var wt = edge.data("wt");
		if (lnum > 1)
		{
			if (bestinc)
			{
				edge.addClass("nodehide");
			}
			else
			{
				if (wt >= minwt)
				{	
					edge.removeClass("nodehide");
				}
				else
				{	
					edge.addClass("nodehide");
				}
			}
		}
		else
		{
			if (wt >= minwt)
			{	
				edge.removeClass("nodehide");
			}
			else
			{	
				edge.addClass("nodehide");
			}
		}
	}
	
}
function hide_nodes_by_cluster(cids_to_keep)
{
<?php
	# This is a kludjy way to prevent hiding of clusters when only one cluster was
	# selected to show to begin with. The logic of the showing/hiding ought to 
	# be revisited and code clarified...but not urgent so far 
	if ($CID_sel == 0)
	{
		echo <<<END
	// Hide the nodes that aren't in one of the given clusters. 
	// We don't have to worry about the edges as cytoscape.js automatically
	// hides edges when one end is hidden...not sure how it does that. 
	var all = cy.elements("node");
	for (j = 0; j < all.length; j++) 
	{
		cynode = all[j];
		if (cynode.data('cid'))
		{
			lvl = cynode.data('lvl');	
			cid = cynode.data('cid');
			if (lvl <= 1)
			{
				if (!cids_to_keep[cid])
				{
					cynode.addClass('nodehide');
				} 
			}
			else
			{
				// higher level node; see if it contains any non-hidden node
				if (clst_cont[cid])
				{
					keep = 0;
					for (k = 0; k < clst_cont[cid].length; k++)
					{
						cid2 = clst_cont[cid][k];
						if (cids_to_keep[cid2])
						{
							keep = 1;
							break;
						}	
					}
					if (keep == 0)
					{
						cynode.addClass('nodehide');
					} 
				
				}
				else
				{
					alert("err:cid=" + cid + " has no content");
				}
			}
		}
	} 
END;
	}
?>
}

function hide_nodes_by_weight(target_wt)
{
	var sel_goterm = $("#sel_goterm");
	var sel_keggterm = $("#sel_keggterm");
	// If there's a go and/or kegg term selected then we need to make
	// sure to NOT un-hide genes that are not in those clusters
	var keggterm = sel_keggterm.val();
	var goterm = sel_goterm.val();
	go_cids_to_keep = new Array();
	kegg_cids_to_keep = new Array();
	if (goterm > 0)
	{
		for (i = 0; i < go2clst[goterm].length; i++)
		{
			cid = go2clst[goterm][i];
			go_cids_to_keep[cid] = 1;
		}
	}
	if (keggterm > 0)
	{
		for (i = 0; i < kegg2clst[keggterm].length; i++)
		{
			cid = kegg2clst[keggterm][i];
			kegg_cids_to_keep[cid] = 1;
		}
	}
	var all_nodes = cy.nodes();
	for (j = 0; j < all_nodes.length; j++) 
	{
		var cynode = all_nodes[j];
		if (cynode.data('id').startsWith("G"))
		{
			var wt = cynode.data("wt");
			var cid = cynode.data("cid");
			if (goterm > 0 && !go_cids_to_keep[cid])
			{
				continue;
			}
			if (keggterm > 0 && !kegg_cids_to_keep[cid])
			{
				continue;
			}
			if (wt > target_wt)
			{
				cynode.removeClass('nodehide');
			}
			else
			{
				cynode.addClass('nodehide');
			}
		}
	}
}
function do_go_gene_ajax_highlighting()
{
	var term = sel_goterm.val();
	if (!go2gene[term])
	{
		ajax_get_gos(term);
	}
	clear_gene_highlight();
	highlight_go_genes(term);
}
function highlight_go_genes(term)
{
	for (var i = 0; i < go2gene[term].length; i++)
	{
		var gid = go2gene[term][i];
		node_highlight(1,"G" + gid,1);
	}
}
function clear_gene_highlight()
{
	var all = cy.elements("node");
	for (i = 0; i < all.length; i++) 
	{
		cynode = all[i];
		if (cynode.data('id').startsWith("G"))
		{
			cynode.removeClass('nodehlt');
		}
	}
}
function ajax_get_gos(term)
{
	$.ajax({
	   type: 'GET',
	   url: 'ajax_go_mapping.php', 
	   data: {"crid" : <?php echo $CRID ?>, "gonum" : term},
	   async: true, 
	   success: function(data){
			go2gene[term] = data["gids"];
			clear_gene_highlight();
			highlight_go_genes(term);
	   },
	   error: function() {
		  alert("ERROR!");
	   }
	});
}
function node_highlight(idnum,idstr,onoff)
{
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
function gene_zoom(idnum,idstr)
{
	if (idnum == 0) 
	{ 
		cy.fit(); 
		return;
	}
	var center_node = cy.getElementById(idstr);
	var cid = center_node.data('cid');
	var gene_wt = parseFloat(center_node.data('wt'));
	var cur_wt = parseFloat($("#txt_mw").val());
	if (gene_wt < cur_wt)
	{
		set_min_wt(gene_wt);
		show_hide_nodes_edges();
	}
	var clst_node = cy.getElementById("C" + cid);
	var fit_set = center_node.union(clst_node);
	var edges = center_node.incomers(function( ele ){
			return ele.data('lnum') == '1';
		});
	cy.fit(fit_set); 
	cy.center(center_node);
}
function node_zoom(idnum,idstr)
{
	if (idnum == 0) 
	{ 
		cy.fit(); 
		return;
	}
	var center_node = cy.getElementById(idstr);
	var edges = center_node.outgoers(function( ele ){
			return ele.data('lnum') == '1';
		});
	cy.fit(edges); 
	cy.center(center_node);
}
function node_highlight2(idnum,idstr,onoff)
{
	if (idnum == 0) {return; }
	var cynode = cy.getElementById(idstr);
	if (onoff == 1)
	{
		//cynode.style('background-color','yellow');
		//cynode.addClass('nodehlt');
	}	
	else	
	{
		//cynode.style('background-color','black');
		cynode.removeClass('nodehlt');
	}	
	/*comps = cynode.components();
	for (i = 0; i < comps.length; i++)
	{
		comp = comps[i];
		alert(comp.size());
		comp.nodes().addClass('nodehlt');	
	}*/
}
function show_all_nodes()
{
	var minwt = $("#txt_mw").val();
	var all = cy.elements("node");
	for (j = 0; j < all.length; j++) 
	{
		cynode = all[j];
		if (cynode.data('id').startsWith('G'))
		{
			if (cynode.data('wt') >= minwt)
			{
				cynode.removeClass('nodehide');
			}
		}
		else
		{
			cynode.removeClass('nodehide');
		}
	} 
}
function add_drag_listeners()
{
	var all = cy.elements("node");
	for (j = 0; j < all.length; j++) 
	{
		cynode = all[j];
		if (cynode.data('cid'))
		{
			cynode.on("grab",handle_grab);
			cynode.on("drag",handle_drag);
		}
	}
}
var grab_x = 0;
var grab_y = 0;
var drag_subgraph = [];
function handle_grab(evt)
{
	grab_x = this.position().x ;	
	grab_y = this.position().y ;	
	var succ = this.successors();
	drag_subgraph = [];
	var succstr = "";
	var cid = this.data('cid');
	for (i = 0; i < succ.length; i++)
	{
		if (succ[i].isNode())
		{
			if (succ[i].data('cid') == cid)
			{	
				var old_x = succ[i].position().x;
				var old_y = succ[i].position().y;
				succstr += " " + succ[i].data("id");
				drag_subgraph.push({old_x:old_x, old_y:old_y, obj:succ[i]});	
			}
		}
	}
}
function handle_drag(evt)
{
	var new_x = this.position().x;
	var new_y = this.position().y;
	var delta_x = new_x - grab_x;
	var delta_y = new_y - grab_y;
	for (i = 0; i < drag_subgraph.length; i++)
	{
		var obj = drag_subgraph[i].obj;
		var old_x = drag_subgraph[i].old_x;
		var old_y = drag_subgraph[i].old_y;
		var new_x = old_x + delta_x;
		var new_y = old_y + delta_y;
		obj.position({x:new_x, y:new_y});
	}
}
function save_wt()
{
	var wt = $("#txt_mw").val();
	$.ajax({
	   type: 'POST',
	   url: 'ajax_save_weight.php', 
	   data: {"crid" : <?php echo $CRID ?>, "wt":wt},
	   async: true, 
	   success: function(data){
			if (data.status == "success")
			{
				alert(data.msg);
			}
	   },
	   error: function(data) {
		  alert(data.msg);
	   }
	});
}
function clear_graph()
{
	$.ajax({
	   type: 'POST',
	   url: 'ajax_clear_graph.php', 
	   data: {"crid" : <?php echo $CRID ?>},
	   async: true, 
	   success: function(data){
			if (data.status == "success")
			{
				alert(data.msg);
			}
			$("#save_graph_btn").prop("disabled",false);
			$("#clear_graph_btn").prop("disabled",false);
	   },
	   error: function(data) {
		  alert(data.msg);
			$("#save_graph_bth").prop("disabled",false);
			$("#clear_graph_btn").prop("disabled",false);
	   }
	});
}
function save_graph()
{
	var all = cy.elements("node");
	var data = [];
	for (j = 0; j < all.length; j++) 
	{
		var cynode = all[j];
		var id = cynode.data("id");
		if (id.startsWith("G") || id.startsWith("C"))
		{
			var x = cynode.position().x;
			var y = cynode.position().y;
			data.push({"id":id,"x":x,"y":y});
		}
	}
	var json = JSON.stringify(data);
	$("#save_graph_btn").prop("disabled",true);
	$("#clear_graph_btn").prop("disabled",true);
	$.ajax({
	   type: 'POST',
	   url: 'ajax_save_graph.php', 
	   data: {"crid" : <?php echo $CRID ?>, "json" : json},
	   async: true, 
	   success: function(data){
			if (data.status == "success")
			{
				alert(data.msg);
			}
			$("#save_graph_btn").prop("disabled",false);
			$("#clear_graph_btn").prop("disabled",false);
	   },
	   error: function(data) {
		  	alert(data.msg);
			$("#save_graph_bth").prop("disabled",false);
			$("#clear_graph_btn").prop("disabled",false);
	   }
	});
}
</script>
</body>


<?php

##############################################################
#
# Gets the graph structure from the DB, then writes out the cytoscape.js data elements
#

function build_graph(&$numNodes,$N,$minWt,&$gids_shown,&$clstContent,&$minwt,&$maxwt)
{
	global $DB, $CRID, $CID_sel, $GID_sel, $Goterm,$pdata,$MinWt;
	global $Keggterm, $go_enrich_pval, $kegg_enrich_pval, $numSizeBins;
	global $level_colors, $MaxClstLvl, $Bestinc, $Use_hugo;

	$limit_cids_where = "";
	$limit_cids_where2 = "";

	$numNodes = 0;
	$minWt = 0; #XXX clean up this code


	# Is our CID list restricted by parameter settings? 
	$restricted_CIDs = (($CID_sel > 0 || $GID_sel > 0 || $Goterm != 0 || $Keggterm != 0) ? 1 : 0);

	# This will hold the CIDs that we're going to show.
	# Note that a level-one cluster may not have any genes attached to it, due to the gene limit. 
	# However, we will show it anyway in the global view, within its level-2 cluster.
	$cids2show = array(); 

	if ($CID_sel > 0)
	{
		$cids2show[$CID_sel] = 1;
		$limit_cids = array();
		get_connected_clst($limit_cids,$CID_sel,$minWt);
		$limit_cids_where = " and CID in (".implode(",",$limit_cids).")";
		$limit_cids_where2 = " and CID1 in (".implode(",",$limit_cids).")";
		$limit_cids_where2 .= " and CID2 in (".implode(",",$limit_cids).")";
		foreach ($limit_cids as $cid)
		{
			$cids2show[$cid] = 1;
		}
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
			$cids2show[$cid] = 1;
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
			if ($CID_sel > 0 && $cid != $CID_sel)
			{
				# If they also specified a cluster then we only show that cluster
				continue;
			}
			$go_cids[] = $cid;
			$cids2show[$cid] = 1;
		}
		$st->close();
		if (count($go_cids) > 0)
		{
			$go_where = " and CID in (".implode(",",$go_cids).")";
		}
		$st = dbps("select g2e.GID from g2e join e2go on e2go.eterm=g2e.term ".
					"join glist on glist.ID=g2e.GID where e2go.gterm=? and glist.GLID=?");
		$st->bind_param("ii",$Goterm,$pdata["GLID"]);
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
			if ($CID_sel > 0 && $cid != $CID_sel)
			{
				# If they also specified a cluster then we only show that cluster
				continue;
			}
			$kegg_cids[] = $cid;
			$cids2show[$cid] = 1;
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
	$elements = array();
	$CIDlist = array(); # for building the next-level query

	# First build the level 1 cluster nodes. The complication is that, due to max gene limit, some
	# will not have any genes, hence will not be found in the gene search below. However,
	# we also have to be careful not to show ALL clusters, since user can specify a cluster, go term, etc

	$color = $level_colors["1"];

	$sql = "select ID, lbl,pos_x,pos_y from clst where CRID=? and lvl=0";
	$st = $DB->prepare($sql);	
	$st->bind_param("i",$CRID);
	$st->bind_result($CID,$cnum,$cx,$cy);
	$st->execute();
	while ($st->fetch())
	{
		if ($restricted_CIDs)
		{
			if (!isset($cids2show[$CID]))
			{
				continue;
			}
		}
		$CIDtag = "C$CID";
		$CIDlbl = "L1_$cnum";
		$elements[] = "{data: {id: '$CIDtag', size:'25px', lbl:'$CIDlbl', cid:'$CID', lvl: '1',".
							"hugo:'$CIDlbl', msg:'cluster:$CIDlbl', ".
				"link:'/ppi.php?CRID=$CRID&CID=$CID&corex_score=$minWt', color:'$color'}, ".
					"position:{x:$cx,y:$cy}}";
				#link:'/ppi/run1/".($CID-1).".stringNetwork.png'}}";
		$nodes[$CIDtag] = 1;
		$CIDlist[] = $CID;
	}
	$st->close();

	$sql = "select GID, CID, glist.lbl as lbl,glist.hugo as hugo, glist.descr as descr, ".
		" mi,wt,clst.lbl as cnum, ".
		" glist.pos_x as gx, glist.pos_y as gy, clst.pos_x as cx, clst.pos_y as cy ".
			" from g2c join glist on glist.ID=g2c.GID ";
	$sql .= " join clst on clst.ID = g2c.CID ";
	$sql .= " where g2c.CRID=? and wt >= ? $go_where $kegg_where $limit_cids_where $gene_cids_where ";
	$sql .=	" order by wt desc ";  # DO NOT CHANGE ORDER
	if ($Bestinc == 0)
	{
		#$sql .= "limit 2000";
	} 
	$st = $DB->prepare($sql);
	$st->bind_param("id",$CRID,$minWt);
	$st->bind_result($GID,$CID,$gene_name,$hugo_name,$gene_desc,$mi,$wt,$cnum,$gx,$gy,$cx,$cy);
	$st->execute();
	$gene_node_data = array();
	$gid2names = array();
	$gid2hugo = array();
	$gid2desc = array();
	$gid2cid = array();
	$gid_seen = array();    
	$max_gid_wt = array();
	while ($st->fetch())
	{
		if (!isset($gid_seen[$GID]))
		{
			$gid_seen[$GID] = 0;
			$max_gid_wt[$GID] = $wt;
			$gid2cid[$GID] = $CID;
		}
		if ($wt < .1*$max_gid_wt[$GID])
		{
			continue;
		}
		$gid_seen[$GID]++;

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
			error_log("Getting additional CIDs in graph! CID=$CID");
			$elements[] = "{data: {id: '$CIDtag', size:'25px', lbl:'$CIDlbl', cid:'$CID', lvl: '1',".
								"hugo:'$CIDlbl', msg:'cluster:$CIDlbl', ".
					"link:'/ppi.php?CRID=$CRID&CID=$CID&corex_score=$minWt', color:'$color'},".
					"position:{x:$cx,y:$cy}}";
					#link:'/ppi/run1/".($CID-1).".stringNetwork.png'}}";
			$nodes[$CIDtag] = 1;
			$CIDlist[] = $CID;
		}	

		# we have to collect all the cluster data before making the gene
		# node entry since the message will list the clusters the gene is in

		$gid2names[$GID] = $gene_name;
		$gid2hugo[$GID] = $hugo_name;
		$gid2desc[$GID] = $gene_desc;
		$wt = sprintf("%.3f",$wt);
		$mi = sprintf("%.3f",$mi);
		$gene_node_data[$GID][] = array("cnum" => $cnum, "cid" => $CID, "wt" => $wt, "mi" => $mi, 
				"x" => $gx, "y" => $gy);

		$links[] = array("targ" => "$GIDtag", "src" => "$CIDtag", 
				"wt" => $wt, "mi" => $mi, "lnum" => $gid_seen[$GID]);
	}
	$st->close();
	foreach ($gene_node_data as $GID => $darray)
	{
		$gname = $gid2names[$GID];
		$hugo = $gid2hugo[$GID];
		$desc = $gid2desc[$GID];
		$info = array();
		$maxWt = 0;
		foreach ($darray as $data)
		{
			$cnum 	= $data["cnum"];	
			$wt 	= $data["wt"];	
			$mi 	= $data["mi"];	
			$x		= $data["x"];	
			$y		= $data["y"];	
			$info[] = "$cnum (Wt=$wt, MI=$mi)";
			if ($wt > $maxWt)
			{
				$maxWt = $wt;
			}
		}
		$GIDtag = "G$GID";
		$msg = ($gname == $hugo ? "gene:$gname" : "gene:$gname ($hugo)");
		if ($desc != "") 
		{
			$msg .= " ".json_encode($desc, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_APOS);
		}
		$nclst = count($info);
		$msg .= "<br>contained in $nclst clusters: ".implode("; ",$info);
		$classes = array();
		if ($maxWt < $MinWt)
		{
			$classes[] = "nodehide";
		}	
		if (isset($go_genes[$GID]))
		{
			$classes[] = "nodehlt";
		}	
		$classes = implode(",",$classes);
		$cid 	= $gid2cid[$GID];	 
		$elements[] = "{data: {id: '$GIDtag', size:'15px', lbl:'$gname', cid:'$cid', lvl:'0', wt:$maxWt,".
						"hugo:'$hugo', msg:'$msg', color:'red'}, ".
						"position:{x:$x,y:$y},".
						"classes:'$classes'}";
		$nodes[$GIDtag] = 1;
		$gids_shown[$GID] = 1;
	}

	#
	# Get the higher level data
	# First we need the number of levels and the cluster numbers
	# NOTE LEVELS IN DB START AT 0 WHILE DISPLAY STARTS AT 1!!!
	# 

	$st = dbps("select max(lvl) as maxlvl from clst where CRID=?");
	$st->bind_param("i",$CRID);
	$st->bind_result($maxlvl);
	$st->execute();
	$st->fetch();
	$st->close();
	$maxLvl = min($maxlvl + 1, $MaxClstLvl);	

	$cid2num = array();
	$cid2x = array(); $cid2y = array();
	$st = $DB->prepare("select ID, lbl, pos_x, pos_y from clst where CRID=?");
	$st->bind_param("i",$CRID);
	$st->bind_result($id,$lbl,$cx,$cy);
	$st->execute();
	while ($st->fetch())
	{
		$cid2num[$id] 	= $lbl;
		$cid2x[$id] 	= $cx;
		$cid2y[$id] 	= $cy;
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
					" $limit_cids_where2 $gene_cids_where2 order by wt desc");  # DON'T CHANGE SORT!!
		$st->bind_param("di",$minWt,$CRID);
		$st->bind_result($CID1,$CID2,$wt,$mi);
		$st->execute();
		while ($st->fetch())
		{
			if (!isset($cid1seen[$CID1]))
			{
				$cid1seen[$CID1] = 0;
			}
			$cid1seen[$CID1]++;

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
				$x = $cid2x[$CID2];
				$y = $cid2y[$CID2];
				$elements[] = "{data: {id: '$CID2tag', size:'$size', lbl:'$CID2lbl', ".
					"hugo:'$CID2lbl', cid:'$CID2',".
					"lvl:'$lvl',link:'', msg:'cluster:$CID2lbl', color:'$color'},".
					"position:{x:$x,y:$y}}";
				$nodes[$CID2tag] = 1;
				$CIDlist[] = $CID2; 
			}
			$links[] = array("targ" => "$CID1tag", "src" => "$CID2tag", "wt" => "$wt", 
					"mi" => "$mi", lnum => $cid1seen[$CID1]);

			# Here we are tracking the lower clusters contained in higher ones.
			# Ultimately this is written to javascript and used for the GO/Kegg show/hide function.
			if (!isset($clstContent[$CID2]))
			{
				$clstContent[$CID2] = array();
			}
			$clstContent[$CID2][$CID1] = 1;
			if (isset($clstContent[$CID1]))
			{
				foreach ($clstContent[$CID1] as $cid => $val)
				{
					$clstContent[$CID2][$cid] = 1;
				}
			}
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
		$lnum =  $data["lnum"];
		$id = $src."_".$targ;

		#$diffwt = log($wt/$minwt); 
		$diffwt = $wt - $minwt; 
		$sizebin = min($numSizeBins,1 + floor($numSizeBins*$diffwt/$wt_range));
		$opacity = 0.2 + (0.8/$numSizeBins)*$sizebin;
		$width = (2*$sizebin)."px";
		$classes = "";
		if ($wt < $MinWt || ($Bestinc && ($lnum > 1)))
		{
			$classes = "nodehide";
		}	
		$elements[] = "{data: { id:'$id', source: '$src', target: '$targ', lnum:'$lnum', wt:'$wt',".
					" msg: 'weight:$wt,MI:$mi', width: '$width', opacity: '$opacity'},".
						"classes:'$classes'}";
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
		'font-size' : '50px',
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
	},
	{
		selector: '.nodeshowtext',
		style: {
			'text-background-color' : 'white',
			'text-background-opacity' : '1.0',
			'font-weight' : 'bold',
			'z-index' : 10
		}
	},
	{
		selector: '.nodehide',
		style: {
			'display':'none'
		}
	}		
	
  ],
END;
	if ($pdata["pos_saved"]==1 && $CID_sel==0 && $GID_sel==0 && $Goterm==0 && $Keggterm==0 )
	{
		$html .= <<<END
layout:{ name: 'preset',
		stop:function(){
			$('#loading').hide();
		}
	}
});
END;

	}
	else
	{
		$html .= <<<END
layout:{ name: 'cose',
		nodeRepulsion: 4000000,
		stop:function(){
			$('#loading').hide();
		}
	}
});
END;
	}
	return $html;
}

function go_enrich_sel($name, $sel,&$go2clst)
{
	global $CRID, $DB, $go_enrich_pval, $Goterm;

	$opts = array();
	$selected = ($Goterm == 0 ? " selected " : "");
	$opts[] = "<option value='0' $selected>none</option>";
	$terms_seen = array();
	$st = dbps("select gos.term as term, gos.descr as descr,clst.id as cid ".
				" from clst2go join gos on gos.term=clst2go.term ".
				" join clst on clst.ID=clst2go.CID ".
				" where clst.CRID=? and clst2go.pval <= ? ".
				" and gos.CRID=? order by term asc, clst.ID asc ",0);
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
	$st = $DB->prepare("select lvl from clst where ID=?");
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
	$st = $DB->prepare("select glist.ID as GID,glist.hugo as gname, glist.lbl as altname, 
					clst.ID as CID,clst.lbl as cnum ".
		"  from glist join g2c on g2c.GID=glist.ID ".
		" join clst on clst.ID=g2c.CID where g2c.CRID=? and g2c.wt >= ? order by gname asc");
	$st->bind_param("id",$CRID,$minwt);
	$st->bind_result($GID,$gname,$altname,$CID,$cnum);
	$st->execute();
	while ($st->fetch())
	{
		if ($gname == "") // in case we didn't locate a hugo name
		{
			$gname = $altname;
		}
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
		#$opts[] = "<option value='$GID' $selected $optstyle>$gname ($cnumstr)</option>";
		$opts[] = "<option value='$GID' $selected $optstyle>$gname</option>";
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
	print "var go2clst = new Array();\n";
	foreach ($g2c as $term => $carr)
	{
		$astr = implode("','",array_keys($carr));
		print "go2clst[$term] = new Array('$astr');\n";
	}

}
function dump_kegg2clst(&$k2c)
{
	print "var kegg2clst = new Array();\n";
	foreach ($k2c as $term => $carr)
	{
		$astr = implode("','",array_keys($carr));
		print "kegg2clst[$term] = new Array('$astr');\n";
	}

}
function dump_clst_content($cc)
{
	print "var clst_cont = new Array();\n";
	foreach ($cc as $cid => $carr)
	{
		$astr = implode("','",array_keys($carr));
		print "clst_cont[$cid] = new Array('$astr');\n";
	}
}

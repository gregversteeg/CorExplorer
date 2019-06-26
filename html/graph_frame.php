<?php
require_once("util.php");
ini_set('memory_limit', '1G');

$CRID = getint("crid",1);
$forceComp = getint("fc",0);

if (!read_access($CRID))
{
	die("access denied");
}


$pdata = array();
load_proj_data($pdata,$CRID);

$MinWt = getnum("mw",$pdata["def_wt"]);

$go_enrich_pval = 0.005;
$kegg_enrich_pval = 0.005;
$MaxClstLvl = 2;   # max level to show (gene = level 0)
$WtStepdownFraction = 0.1;  # minimum fraction of best weight for additional inclusions to be shown

$numSizeBins = 5;		# edge size bins

$go2clst = array();
$kegg2clst = array();

?>

<head>
<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
<link rel="stylesheet" type="text/css" href="/font.css"> 
<style>
    #cy {
        width: 100%;
        height: 100%;
        position: relative;
    }
	#foot {
        width: 80%;
        position: absolute;
        top: 95%;
        left: 10%;
	}
	.ui-button:focus { outline:none !important }
	.ui-dialog 
	{
		padding:5px;
	}
	.ui-dialog .ui-dialog-content 
	{
		padding:5px;
	}
	.ui-dialog .ui-dialog-titlebar 
	{
		height: 20px;
		padding:1px 0px 0px 10px;
	}
	.ui-dialog .ui-dialog-titlebar .ui-dialog-title
	{
		font-size:12px;
	}
	.ui-dialog .ui-dialog-titlebar .ui-dialog-titlebar-close
	{
		position:relative;
		top:15px;
		height: 7px; 
		width: 7px; 
		text-decoration:none;
		border:none;
	}
	html, body
	{
		margin: 0px;
		padding: 0px;
		font-family:sans-serif;
	}
</style>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.1.2/cytoscape.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
</head>
<body>

<table width="100%" cellspacing=0 cellpadding=0>
	<tr>
		<td valign="top" align="left" style="padding-left:3px">
		<table cellspacing=0 cellpadding=0 >
			<tr>
				<td align="left" title="<?php print tip_text('clst_sel') ?>"> 
						Factor: <?php print clst_sel("cid",0,-1,"--all--") ?> </td>
				<td align="left"  title="<?php print tip_text('gene_sel') ?>" style="padding-left:25px">
						Gene: <?php print gene_sel("gid",0,0,$gids_shown) ?> 
				</td>
				<td>&nbsp;
				</td>
			</tr>
			<tr >
				<td align="left" valign="top" style="padding-top:3px" 
										title="<?php print tip_text('kegg_sel')?>">
					<table cellspacing=0 cellpadding=0>
						<tr>
							<td >Kegg enriched:</td>
						</tr>
						<tr>
							<td><?php print kegg_enrich_sel("keggterm",0,$kegg2clst) ?></td>
						</tr>
					</table>
				</td>
				<td align="left"  valign="top" style="padding-left:25px;padding-top:3px;" 
										title="<?php print tip_text('go_sel')?>">
					<table cellspacing=0 cellpadding=0>
						<tr>
							<td >GO enriched:</td>
						</tr>
						<tr>
							<td ><?php print go_enrich_sel("goterm",0,$go2clst) ?>
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
							<td valign="bottom" title="<?php print tip_text('wt_slider')?>" >Link Weight: </td>
							<td  valign="bottom" style="width:150px;padding-left:10px" 
									 title="<?php print tip_text('wt_slider')?>"><div id="mw_slider"></div> </td>
							<td valign="bottom"  style="padding-left:3px" title="<?php print tip_text('wt_slider')?>">
								 <input name="mw" id="txt_mw" type="text" size="3" value="<?php print $MinWt ?>">
							</td>
							<td valign="bottom"  style="padding-left:10px;" title="<?php print tip_text('hugo_names') ?>">HUGO names:
								 <input name="use_hugo" id="use_hugo_chk" type="checkbox" checked>
							</td>
							<!--td  valign="bottom" style="padding-left:10px;" 
									title="<?php print tip_text('best_inc')?>" >Best inclusion only:
								 <input name="bestinc" id="chk_bestinc" type="checkbox" checked>
							</td-->
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
$('#popout_btn').click(function()
{
 	var win = window.open(window.location.href, '_blank');
  	win.focus();
});
</script>
<?php

if (0) #$pdata["pos_saved"] == 0)
{
	preset_positions();
}
$gids_shown = array();
$actualMinWt = $MinWt;
$actualMaxWt = 1;
$graph_html = build_graph($gids_shown,$actualMinWt,$actualMaxWt);

?>
<script>
<?php
	print $graph_html;
	
	dump_clst_content();
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
var extra_links = [];
var extra_nodes = [];
var tip_links = "Click this link to load all links for genes in this factor. " +
				" (Only the highest-weight links are currently being shown.)";
var tip_genes = "Click this link to show all genes in this factor. " +
				" (Only the genes for which this factor is their best factor are currently being shown.)";
cy.on('click', 'node', function(evt)
{
	var x = evt.renderedPosition.x;
	var y = evt.renderedPosition.y;
	var cid = this.data('cid');
	var lvl = this.data('lvl');

	if (lvl == 1 || lvl == 2)
	{
		var content = "";
		if (!extra_links[cid])
		{
			content = "<div><span title='" + tip_links + "' onclick='ajax_getlinks(this," + cid + "," + lvl 
						+ ");'><span style = 'text-decoration:underline;color:blue;'>" +
					"Load&nbsp;Additional&nbsp;Links<span></span></div>";
		}
		if (lvl ==1 && !extra_nodes[cid])
		{
			content += "<p><div><span title='" + tip_genes + "' onclick='ajax_getnodes(this," + cid   
						+ ");'><span style = 'text-decoration:underline;color:blue;'>" +
					"Load&nbsp;Additional&nbsp;Genes<span></span></div>";
		}
		var html = ' <div id="nodectxt" title="' + this.data('lbl') + '" style="overflow:hidden;padding:0px" > ' +
			content  +
			'</div> ';
		$(html).dialog({height:200, width:200, position:{my:"left top",at:"left+" + x + " top+" + y, of:"body"}});
	}
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
//$('#chk_bestinc').change(function() 
//{
//	show_hide_nodes_edges();
//});
$('#use_hugo_chk').change(function() 
{
	update_gene_labels();
}); 
function unlock_nodes(cid,pos)
{
}
		
function update_gene_labels()
{
 	var all = cy.elements("node");
	var use_hugo = $('#use_hugo_chk').prop('checked');
   	for (i = 0; i < all.length; i++) 
	{
       	the_node = all[i];
		if (the_node.data('lvl') == '0')
		{
			if (use_hugo)
			{
				the_node.addClass('altlbl');
			}
			else
			{
				the_node.removeClass('altlbl');
			}
		}
   	}

}

// Maps of GOs to genes, clusters and Keggs to clusters...filled out through ajax 
var go2gene = [];
var go2clst = []; 
var kegg2clst = []; 

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
		var toplevel_cid = (typeof top_level_clst[cur] !== 'undefined');
		if (toplevel_cid)
		{
			cy.fit(); 
			show_hide_nodes_edges();
		}
		else
		{
			node_highlight(cur,"C" + cur,1);
			node_zoom(cur,"C" + cur);
		}
	});

	sel_goterm.change(function(data)
	{
		var term = $(this).val();

		clear_gene_highlight();
		if (term == 0)
		{
			show_hide_nodes_edges();
		}
		else
		{
			if (!go2gene[term])
			{
				ajax_get_gos(term);
			}
			else
			{
				highlight_go_genes(term);
				show_hide_nodes_edges();
			}	
		}

	});

	sel_keggterm.change(function(data)
	{
		var term = $(this).val();
		if (term == 0)
		{
			show_hide_nodes_edges();
		}
		else
		{
			if (!kegg2clst[term])
			{
				ajax_get_kegg(term);
			}
			else
			{
				show_hide_nodes_edges();
			}	
		}
	});

	$("#txt_mw").change(function(data)
	{
		var txtval = slider_to_log(parseFloat($(this).val()));
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
	//$("#chk_bestinc").prop("checked",true);
	$("#use_hugo_chk").prop("checked",true);
	$("#sel_cid").val("0");
	$("#sel_keggterm").val("0");
	$("#sel_goterm").val("0");
	$("#sel_gid").val("0");
	update_gene_labels();
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
	var bestinc = false; //$('#chk_bestinc').prop("checked"); 
	var keggterm = $("#sel_keggterm").val();
	var goterm = $("#sel_goterm").val();
	var minwt = parseFloat($("#txt_mw").val());
	var sel_cid = $("#sel_cid").val();

	var toplevel_cid = (typeof top_level_clst[sel_cid] !== 'undefined');
	//
	// If there is a GO or Kegg selected, then we need the
	// relevant set of clusters.
	// Likewise if a top-level cluster was selected.
	go_cids_to_keep = new Array();
	kegg_cids_to_keep = new Array();
	toplevel_cids_to_keep = new Array();
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
	if (toplevel_cid)
	{
		for (i = 0; i < clst_cont[sel_cid].length; i++)
		{
			cid = clst_cont[sel_cid][i];
			toplevel_cids_to_keep[cid] = 1;
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
				if (wt >= minwt || minwt == 0)
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
			if (wt >= minwt || minwt == 0)
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
		var lvl = node.data('lvl');	
		var cid = node.data('cid');
		if (lvl == 0)
		{
			// Gene node
			var wt = node.data("wt");
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
			if (toplevel_cid && !toplevel_cids_to_keep[cid])
			{
				node.addClass('nodehide');
				continue;
			}
			if (wt >= minwt || minwt == 0)
			{
				node.removeClass('nodehide');
			}
			else
			{
				node.addClass('nodehide');
			}
		}
		else if (lvl == 1)
		{
			// First-level cluster
			keep = 1;
			if (goterm > 0 && !go_cids_to_keep[cid])
			{
				keep = 0;
			} 
			if (keggterm > 0 && !kegg_cids_to_keep[cid])
			{
				keep = 0;
			} 
			if (toplevel_cid && !toplevel_cids_to_keep[cid])
			{
				node.addClass('nodehide');
				continue;
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
			// higher level cluster; see if it contains any non-hidden node
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
					if (toplevel_cid && !toplevel_cids_to_keep[cid])
					{
						node.addClass('nodehide');
						continue;
					}
					if (keep2)
					{
						keep = 1;
						break;
					}	
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
				console.log("err:cid=" + cid + " has no content");
			}
		}
	}
}
function highlight_go_genes(term)
{
	if (term == 0) return;
	for (var i = 0; i < go2gene[term].length; i++)
	{
		var gid = go2gene[term][i];
		gene_node_highlight(1,"G" + gid,1);
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
function ajax_getlinks(obj,cid,lvl)
{
	obj.innerHTML = "Loading...";
	$.ajax({
	   type: 'GET',
	   url: 'ajax_getlinks.php', 
	   data: {"crid" : <?php echo $CRID ?>, "cid" : cid, "lvl" : lvl},
	   async: true, 
	   success: function(data){
			//alert(JSON.stringify(data["links"]));
			extra_links[cid] = 1;
			add_extras(data["links"]);
			for (cid1 in data["cids"])
			{
				// For a level 2 cluster, we mark all the level 1 as done too
				extra_links[cid1] = 1;
			}
			obj.innerHTML = "Done";
	   },
	   error: function() {
			obj.innerHTML = "An error occurred";
	   }
	});
}
var ajax_new_nodes;
var ajax_new_links;
var ajax_add_cid;
var ajax_update_obj;
function ajax_getnodes(obj,cid,lvl)
{
	obj.innerHTML = "Retrieving data...";
	$.ajax({
	   type: 'GET',
	   url: 'ajax_getnodes.php', 
	   data: {"crid" : <?php echo $CRID ?>, "cid" : cid},
	   async: true, 
	   success: function(data){
			//console.log(data["nodes"].length + "," + data["links"].length);
			//console.log(JSON.stringify(data["nodes"]));
			var msg = "Adding " + data["nodes"].length + " genes...";
			obj.innerHTML = msg; 
			// now we have to desynchronize the operation or the innerHTML we just
			// set will not be rendered
			ajax_add_cid = cid;
			ajax_new_nodes = data["nodes"].slice(0);	
			ajax_new_links = data["links"].slice(0);	
			ajax_update_obj = obj;
			extra_nodes[cid] = 1;
			setTimeout(add_extra_nodes,100);
	   },
	   error: function() {
			alert("error");
			obj.innerHTML = "An error occurred";
	   }
	});
}
function add_extra_nodes()
{
	var cid = ajax_add_cid;
	var nodes = ajax_new_nodes;
	var links = ajax_new_links;
	var goterm = $("#sel_goterm").val();

	var cur_pan = cy.pan();
	var cur_zoom = cy.zoom();
	var clbl = 'C' + cid;
	cy.nodes().lock();

	var center_node = cy.$('#' + clbl);
	var factor_nodes = center_node.successors().nodes();
//alert(JSON.stringify(factor_nodes.boundingBox()));
	var bbw = factor_nodes.boundingBox().w;
	var bbh = factor_nodes.boundingBox().h;
	var r = Math.sqrt(bbw*bbw + bbh*bbh)/3.0;
	
	var N = nodes.length;
	var theta = (2*Math.PI)/N;
	for (i = 0; i < nodes.length; i++)
	{
		nodes[i].position.x = center_node.position('x') + Math.floor(r*Math.cos(i*theta));
		nodes[i].position.y = center_node.position('y') + Math.floor(r*Math.sin(i*theta));
	}	
	cy.add(nodes);
	cy.add(links);

	var top_node = center_node.predecessors().nodes(); // the 2nd level node
	//var factor_eles = top_node.successors().union(top_node);
	var factor_eles = center_node.successors().union(center_node);
	var layout = factor_eles.layout({ name: 'cose',
		randomize:false,
		nodeRepulsion:400000,
		edgeElasticity:100,
		nodeDimensionsIncludeLabels:true,
		//numIter:2,
		nodeOverlap:100,
		stop:function(){
			cy.pan(cur_pan);
			cy.zoom(cur_zoom);
			cy.nodes().unlock();
			show_hide_nodes_edges();
			highlight_go_genes(goterm);
			ajax_update_obj.innerHTML = "Done";
		}
	});
	layout.run();
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
			go2clst[term] = data["cids"];
			clear_gene_highlight();
			highlight_go_genes(term);
			show_hide_nodes_edges();
	   },
	   error: function() {
		  alert("ERROR!");
	   }
	});
}
function ajax_get_kegg(term)
{
	$.ajax({
	   type: 'GET',
	   url: 'ajax_go_mapping.php', 
	   data: {"crid" : <?php echo $CRID ?>, "keggnum" : term},
	   async: true, 
	   success: function(data){
			kegg2clst[term] = data["cids"];
			show_hide_nodes_edges();
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
function gene_node_highlight(idnum,idstr,onoff)
{
	if (idnum == 0) {return;}
	var nodes = cy.nodes();
	for (var i = 0; i < nodes.length; i++)
	{
		var id = nodes[i].data("id");
		if (id == idstr || id.startsWith(idstr + '_'))
		{
			if (onoff == 1)
			{
				nodes[i].addClass('nodehlt');
			}	
			else	
			{
				nodes[i].removeClass('nodehlt');
			}	
		}
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

function add_drag_listeners()
{
	var all = cy.elements("node");
	for (j = 0; j < all.length; j++) 
	{
		cynode = all[j];
		cynode.on("grab",handle_grab);
		cynode.on("drag",handle_drag);
	}
}
var grab_x = 0;
var grab_y = 0;
var drag_subgraph = [];
function handle_grab(evt)
{
	//
	// For single-gene drag, we don't have to do anything. 
	// If they drag a cluster, we need to add all its sub-clusters and contained genes
	// to the drag_subraph (only for level 1/2 clusters)
	//
	var lvl = parseInt(this.data('lvl'));
	var cid = parseInt(this.data('cid'));
	grab_x = this.position().x ;	
	grab_y = this.position().y ;	
	drag_subgraph = [];
	if (lvl == 1)
	{
		var succ = this.successors();
		for (i = 0; i < succ.length; i++)
		{
			if (succ[i].isNode())
			{
				// Note that a gene node has its "cid" data value set to its primary parent cluster
				if (succ[i].data('cid') == cid)
				{	
					var old_x = succ[i].position().x;
					var old_y = succ[i].position().y;
					drag_subgraph.push({old_x:old_x, old_y:old_y, obj:succ[i]});	
				}
			}
		}
	}
	else if (lvl == 2)
	{
		// For level 2 cluster, we get the list of its level 1 contents, and drag that
		for (j = 0; j < clst_cont[cid].length; j++)
		{
			var cid2 = clst_cont[cid][j];
			var id2 = "C" + cid2;
			var node2 = cy.getElementById(id2);

			// First add the level 1 cluster
			var old_x = node2.position().x;
			var old_y = node2.position().y;
			drag_subgraph.push({old_x:old_x, old_y:old_y, obj:node2});	

			// Now add all of its genes using same code as above
			var succ = node2.successors();
			for (i = 0; i < succ.length; i++)
			{
				if (succ[i].isNode() && succ[i].data('lvl') == '0')
				{
					if (succ[i].data('cid') == cid2)
					{	
						var old_x = succ[i].position().x;
						var old_y = succ[i].position().y;
						drag_subgraph.push({old_x:old_x, old_y:old_y, obj:succ[i]});	
					}
				}
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
//
// Next functions handle saving default layout and weight to database
//
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

function build_graph(&$gids_shown,&$minwt,&$maxwt)
{
	global $DB, $CRID, $pdata,$MinWt,$forceComp;
	global $go_enrich_pval, $kegg_enrich_pval;
	global $MaxClstLvl, $WtStepdownFraction;

	$numNodes = 0;

	$links = array();  	# The best inclusion links
	$elements = array();

	$links2 = array();	# Rest of the links
	$elements2 = array();

	$CIDlist = array(); # for building the next-level query
	$cid2num = array();
	$cid2x = array(); $cid2y = array();
	$cid2lvl = array();

	# First build all the cluster nodes. 

	$sql = "select ID, lbl,pos_x,pos_y,lvl from clst where CRID=? and lvl < ?";
	$st = $DB->prepare($sql);	
	$st->bind_param("ii",$CRID,$MaxClstLvl);
	$st->bind_result($CID,$cnum,$cx,$cy,$lvl);
	$st->execute();
	while ($st->fetch())
	{
		$lvlnum = $lvl + 1;
		$CIDtag = "C$CID";
		$CIDlbl = "L$lvlnum"."_$cnum";
		$elements[] = "{data: {id: '$CIDtag', lbl:'$CIDlbl', cid:'$CID', lvl: '$lvlnum', msg:'cluster:$CIDlbl'}, position:{x:$cx,y:$cy}}";
		$CIDlist[] = $CID;
		$cid2num[$CID] 	= $cnum;
		$cid2x[$CID] 	= $cx;
		$cid2y[$CID] 	= $cy;
		$cid2lvl[$CID] 	= $lvl;
	}

	$sql = "select GID, CID, glist.lbl as lbl,glist.hugo as hugo, glist.descr as descr, ".
		" mi,wt,clst.lbl as cnum, ".
		" glist.pos_x as gx, glist.pos_y as gy, clst.pos_x as cx, clst.pos_y as cy ".
			" from g2c join glist on glist.ID=g2c.GID ";
	$sql .= " join clst on clst.ID = g2c.CID ";
	$sql .= " where g2c.CRID=?   ";
	$sql .=	" order by wt desc ";  # DO NOT CHANGE ORDER

	$st = $DB->prepare($sql);
	$st->bind_param("i",$CRID);
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
		if ($wt < $WtStepdownFraction*$max_gid_wt[$GID])
		{
			#continue;
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

		# we have to collect all the cluster data before making the gene
		# node entry since the message will list the clusters the gene is in

		if ($hugo_name == "")
		{
			$hugo_name = $gene_name;
		}
		$gid2names[$GID] = $gene_name;
		$gid2hugo[$GID] = $hugo_name;
		$gid2desc[$GID] = $gene_desc;
		$wt = sprintf("%.3f",$wt);
		$mi = sprintf("%.3f",$mi);
		$gene_node_data[$GID][] = array("cnum" => $cnum, "cid" => $CID, "wt" => $wt, "mi" => $mi, 
				"x" => $gx, "y" => $gy);

		$lnum = $gid_seen[$GID];
		if ($lnum == 1)
		{
			$links[] = array("targ" => "$GIDtag", "src" => "$CIDtag", 
				"wt" => $wt, "mi" => $mi, "lnum" => $lnum);
		}
		else
		{
			#$links2[] = array("targ" => "$GIDtag", "src" => "$CIDtag", 
			#	"wt" => $wt, "mi" => $mi, "lnum" => $lnum);
		}
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
		$msg .= "<br>contained in $nclst factors: ".implode("; ",$info);
		$classes = array();
		$classes[] = "altlbl"; # make hugo name be default
		if ($maxWt < $MinWt)
		{
			$classes[] = "nodehide";
		}	
		$classes = implode(" ",$classes);
		$cid 	= $gid2cid[$GID];	 
		$elements[] = "{data: {id: '$GIDtag',  lbl:'$gname', cid:'$cid', lvl:'0', wt:$maxWt,".
						"hugo:'$hugo', msg:'$msg'}, ".
					"position:{x:$x,y:$y},".
					"classes:'$classes'}";
		$gids_shown[$GID] = 1;
	}

	#
	# Get the higher level link data
	# 

	$cid1seen = array();
	$max_cid_wt = array();

	$st = $DB->prepare("select CID1, CID2,wt,mi from c2c where  CRID=?  ".
				"  order by wt desc");  # DON'T CHANGE SORT!!
	$st->bind_param("i",$CRID);
	$st->bind_result($CID1,$CID2,$wt,$mi);
	$st->execute();
	while ($st->fetch())
	{
		if (!isset($cid2lvl[$CID1]) ||
			!isset($cid2lvl[$CID2]))
		{
			continue;
		}
		if (!isset($cid1seen[$CID1]))
		{
			$cid1seen[$CID1] = 0;
			$max_cid_wt[$CID1] = $wt;
		}
		if ($wt < $WtStepdownFraction*$max_cid_wt[$CID1])
		{
			continue;
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
		$lnum = $cid1seen[$CID1];
		if ($lnum == 1)
		{
			$links[] = array("targ" => "$CID1tag", "src" => "$CID2tag", "wt" => "$wt", 
				"mi" => "$mi", "lnum" => $lnum);
		}
		else
		{
			#$links2[] = array("targ" => "$CID1tag", "src" => "$CID2tag", "wt" => "$wt", 
			#	"mi" => "$mi", "lnum" => $lnum);
		}

	}
	$st->close();
	
	
	#$wt_range = log($maxwt/$minwt);
	$wt_range = $maxwt; # - $minwt;
	
	$idnum = 0;
	foreach ($links as $data)
	{
		$src =  $data["src"];
		$targ =  $data["targ"];
		$wt =  $data["wt"];
		$mi =  $data["mi"];
		$lnum =  $data["lnum"];
		#$id = $src."_".$targ;
		$idnum++;

		calc_link_params($wt,$width,$opacity);
		$classes = "";
		if ($wt < $MinWt || $lnum > 1)
		{
			$classes = "nodehide";
		}	
		$elements[] = "{data: { source: '$src', target: '$targ', lnum:'$lnum', wt:'$wt',id: '$idnum',".
					" msg: 'weight:$wt,MI:$mi', width: '$width', opacity: '$opacity'},".
						"classes:'$classes'}";
	}
	foreach ($links2 as $data)
	{
		$src =  $data["src"];
		$targ =  $data["targ"];
		$wt =  $data["wt"];
		$mi =  $data["mi"];
		$lnum =  $data["lnum"];
		$id = $src."_".$targ;
		$idnum++;

		calc_link_params($wt,$width,$opacity);
		$classes = "";
		if ($wt < $MinWt || $lnum > 1)
		{
			$classes = "nodehide";
		}	
		$elements2[] = "{data: { id:'$id', source: '$src', target: '$targ', lnum:'$lnum', wt:'$wt', id: '$idnum',".
					" msg: 'weight:$wt,MI:$mi', width: '$width', opacity: '$opacity'},".
						"classes:'$classes'}";
	}
	$html = <<<END
var cy = cytoscape({
  container: document.getElementById('cy'),
//autoungrabify: 'true',
 wheelSensitivity: .1,

END;
	$html .= "elements: [".implode(",\n",$elements)."],\n";
	$html .= <<<END
style:[ 
    {
      selector: 'node',
      style: {
        'label': 'data(lbl)',
		'font-size' : function(ele){return (ele.data('id').startsWith('C') ? '100px' : '50px');},
		'background-color' : function(ele){
			var lvl = parseInt(ele.data('lvl'));
			if (ele.data('xtra'))
			{
				return 'orange';
			}
			else
			{
				if (lvl >= 2)
				{
					return 'blue';
				}
				else if (lvl == 1)
				{
					return 'black';
				}
				else
				{
					return 'red';
				}
			}
		},
		'width' : function(ele){return (ele.data('id').startsWith('C') ? '50px' : '15px');},
		'height' : function(ele){return (ele.data('id').startsWith('C') ? '50px' : '15px');}
      }
    },
	{
		selector: '.altlbl',
		style: {
        	'label': 'data(hugo)'
		}
	},
    {
      selector: 'edge',
      style: {
        'width': 'data(width)',
		'opacity' : 'data(opacity)',
        'line-color': 'green'
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
	if ($pdata["pos_saved"]==1 && $forceComp==0 )
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
		nodeRepulsion:4000000,
		randomize:true,
		stop:function(){
		//	add_extras();
			$('#loading').hide();
		}
	}
});

END;
	}
	#$html .= "var other_links = [".implode(",\n",$elements2)."];\n";
	$html .= <<<END
function add_extras(links)
{
	var minwt = $("#txt_mw").val();
	for (i = 0; i < links.length; i++)
	{
		var link = links[i];
		if (parseFloat(link.data.wt) >= minwt)
		{
			link.classes = "";
		}
		else
		{
			link.classes = "nodehide";
		}
	}
	cy.add(links);
}
END;
	return $html;
}

function go_enrich_sel($name, $sel,&$go2clst)
{
	global $CRID, $DB, $go_enrich_pval;

	$opts = array();
	$opts[] = "<option value='0' selected>none</option>";
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
		$goname = go_name($term);
		$opts[] = "<option value='$term' >$goname $descr</option>";
	}
	$st->close();
	return "<select name='$name' id='sel_$name'>\n".implode("\n",$opts)."\s</select>\n";
}
function kegg_enrich_sel($name, $sel,&$kegg2clst)
{
	global $CRID, $DB, $kegg_enrich_pval;

	$opts = array();
	$opts[] = "<option value='0' selected>none</option>";
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
		$keggname = kegg_name($term);
		$opts[] = "<option value='$term'>$keggname $descr</option>";
	}
	$st->close();
	return "<select name='$name' id='sel_$name'>\n".implode("\n",$opts)."\s</select>\n";
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
# TODO: iterate more correctly to remove 2 level limit
#		use wts so can include other than best-inclusion
function dump_clst_content()
{
	global $CRID, $MaxClstLvl;

	print "var clst_cont = new Array();\n";

	$clstContent = array();
	$cid1seen = array();

	# 
	# From the database we fill out the cluster contain tree, taking only the best inclusion.
	# Then when we write it to javascript, we iterate two levels, so level 2 clusters
	# get their full contents. 
	#
	$st = dbps("select CID1, CID2,wt from c2c where  CRID=?  order by wt desc");  # DON'T CHANGE SORT!!
	$st->bind_param("i",$CRID);
	$st->bind_result($CID1,$CID2,$wt);
	$st->execute();
	while ($st->fetch())
	{
		if (!isset($cid1seen[$CID1]))
		{
			$cid1seen[$CID1] = 0;
		}
		$cid1seen[$CID1]++;

		if (!isset($clstContent[$CID2]))
		{
			$clstContent[$CID2] = array();
		}
		if ($cid1seen[$CID1] == 1)
		{
			$clstContent[$CID2][$CID1] = 1;
			if (isset($clstContent[$CID1]))
			{
				foreach ($clstContent[$CID1] as $cid => $val)
				{
					$clstContent[$CID2][$cid] = 1;
				}
			}
		}
	}
	$st->close();
	# Iterate 2 levels to fill out up to level 2...should really be done recursively but this is easier for now
	foreach ($clstContent as $CID2 => $carr)
	{
		$full_cont = array();
		foreach ($carr as $CID1 => $val)
		{
			$full_cont[$CID1] = 1;
			if (isset($clstContent[$CID1]))
			{
				foreach ($clstContent[$CID1] as $CID1A => $val2)
				{
					$full_cont[$CID1A] = 1;
					if (isset($clstContent[$CID1A]))
					{
						foreach ($clstContent[$CID1A] as $CID1B => $val3)
						{
							$full_cont[$CID1B] = 1;
						}
					}
				}
			}
		}
		$astr = implode("','",array_keys($full_cont));
		print "clst_cont[$CID2] = new Array('$astr');\n";
	}
	print "var top_level_clst = new Array();\n";
	$st = dbps("select id from clst where crid=? and lvl=?");
	$st->bind_param("ii",$CRID,$MaxClstLvl);
	$st->bind_result($CID);
	$st->execute();
	while ($st->fetch())
	{
		print "top_level_clst[$CID] = 1;\n";
	}
	$st->close();
	
}

<?php
#
# This page takes builds the PPI graph
# using StringDB links stored in our DB. 
# Note the links are between Ensembl proteins, denoted by "EID" in code.
#
# TODO: why can't make the cy box take more of available space??
#		why setting width=100% etc causes graph to draw so small?
#
require_once("util.php");

$CID = getint("cid",0);
$CRID = getint("crid",0);
$Min_ppi_score = getint("ppi_score",400);
$Min_corex_score = getnum("corex_score",0.05);
$Max_genes = getint("ng",1000);
$Multi_map = getval("mm",0);
$Use_outside_links = getval("outside",0);
$Node_lbl_type = getval("node_lbl_type","hugo");

if (!read_access($CRID))
{
	die("access denied");
}

if ($CID != 0)
{
#die("<b>cid=$CID, crid=$CRID</b>");
}

?>

<head>
<style>
    #cy {
        width: 90%; 
        height: 90%;
        position: relative;
		border:2px solid gray;
    }
		
</style>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.1.2/cytoscape.js"></script>
<script src="http://cdn.jsdelivr.net/qtip2/3.0.3/basic/jquery.qtip.min.js"></script>
</head>
<body>
<form method="get">
<input type="hidden" name="crid" value="<?php print $CRID ?>">
<input type="hidden" name="ft" value="<?php echo $FT ?>">
<table cellpadding=5>
	<tr>
		<td><b>PPI:</b></td>
		<td> Factor: <?php print clst_sel("cid",$CID,0,"--choose--") ?> </td>
		<td align="right" style="font-size:1.4em; padding-left:50px;color:#333333" >
			<span id="param_btn" title="Edit parameters" style="cursor:pointer">&nbsp;&#x270e;&nbsp;</span>
			<span id="popout_btn" title="Open in a new page" style="cursor:pointer">&nbsp;&#9654;&nbsp;</span>
		</td>
	</tr>
</table>

<table id="params" >
	<tr>
		<td>Minimum CorEx link weight <input name="corex_score" type="text" size="4" 
					value="<?php print $Min_corex_score ?>">
		</td>
		<td colspan=2 align=left>Minimum StringDB link score <input name="ppi_score" type="text" size="4" 
					value="<?php print $Min_ppi_score ?>">
		</td>
	</tr>
	<tr>
		<td >Label genes by: <?php echo node_lbl_sel("node_lbl_type",$Node_lbl_type) ?> </td>
		<td title="<?php print tip_text('multimap') ?>" >Show all proteins 
					<input type="checkbox" name="mm" <?php checked($Multi_map,0) ?>  >
		<td><input type="submit" value="Apply" ></td>
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
$('#node_lbl_type').change(function() 
{
	newval= $('#node_lbl_type').val();
 	var all = cy.elements("node");
   	for (i = 0; i < all.length; i++) 
	{
       	the_node = all[i];
		the_node.removeClass('genelbl');
		the_node.removeClass('hugolbl');
		the_node.removeClass('ensplbl');
		if (newval == 'hugo')
		{
			the_node.addClass('hugolbl');
		}
		else if (newval == 'gene')
		{
			the_node.addClass('genelbl');
		}
		else if (newval == 'ensp')
		{
			the_node.addClass('ensplbl');
		}
	}
});
</script>

<?php
if ($CID == 0)
{
	exit(0);
}

$ppi_links = array();
$ppi_hugo = array();
$ppi_nodes = array();
$ens_desc = array(); # ensembl prot descripts
build_ppi($ppi_nodes,$ppi_links,$ppi_hugo,$ens_desc,$CID);
$numProts = count($ppi_nodes);
if ($numProts == 0)
{
	print "<p>Cannot generate PPI network (no Ensembl proteins mapped)<p>";
	exit(0);
}
if (count($ppi_links) == 0)
{
	print "<p>No PPI Graph!<p>The $numProts mapped Ensembl proteins have no links<p>";
	exit(0);	

}

?>

<table width="90%" height="90%">
	<tr>
		<td width="100%" height="95%">
			<div id="cy">Drawing...</div>
		</td>
	</tr>
	<tr>
		<td> <?php print stringdb_link($ppi_nodes); ?>
		</td>
	</tr>
</table>
<script>
<?php
$prots = array();
ppi_script($script_code, $ppi_nodes, $ppi_links,$ppi_hugo,$ens_desc);
print "$script_code\n";
?>
cy.on('mouseover', 'node', function(evt)
{
	$("#msg").html(this.data('msg'))
});
cy.on('mouseout', 'node', function(evt){$("#msg").html("")});
/* $('#node_lbl_type').change(function() {
	var sel = $("#node_lbl_type option:selected").val();
	for (var node in cy.elements('node'))
	{
		alert(node);
	}
}); */
</script>
</body>

<?php

function build_ppi(&$ppi_nodes,&$ppi_links,&$ppi_hugo,&$ens_desc,$CID)
{
	global $Min_corex_score,$Multi_map,$Max_genes,$Min_ppi_score,$CRID,$Use_outside_links;

	#
	# First get the genes and proteins from the cluster, subject to score, multimap, and max_gene setting
	# Currently, only lvl 0 clusters
	#

	$genes_seen = array();
	$EIDlist = array();
	$st = dbps("select g2c.GID as GID, g2e.term as EID, glist.lbl as gname,glist.hugo as hname, ".
				" eprot.descr as edesc ".
				" from g2c join g2e on g2c.GID=g2e.GID join glist on glist.ID=g2c.GID ".
				" right join eprot on eprot.term=g2e.term ".
				" where g2c.CID=? and g2c.wt >= ? and g2c.CRID=? ".
				" order by g2c.wt desc ");
	$st->bind_param("idi",$CID,$Min_corex_score,$CRID);
	$st->bind_result($GID,$EID,$gname,$hname,$edesc);
	$st->execute();
	while ($st->fetch())
	{
		if (isset($genes_seen[$GID]))
		{
			if (!$Multi_map)
			{
				continue;
			}
		}
		$genes_seen[$GID] = 1;

		$ppi_nodes[$EID] = $gname;
		$ppi_hugo[$EID] = $hname;
		$EIDlist[$EID] = 1;
		$ens_desc[$EID] = $edesc;

		if (count($genes_seen) == $Max_genes)
		{
			break;
		}
	}
	$st->close();
	if (count($EIDlist) == 0)
	{
		return;
	}
	#
	# Now get the ppi links for each of the EIDs we retrieved
	#
	$extra_EID = array();
	$EIDset = "(".implode(",",array_keys($EIDlist)).")";
	$st = dbps("select ID1, ID2, score from ppi where ID1 in $EIDset and score >= ?");
	$st->bind_param("d",$Min_ppi_score);
	$st->bind_result($EID1,$EID2,$score);
	$st->execute();
	while ($st->fetch())
	{
		if (!isset($EIDlist[$EID2]))
		{
			if (!$Use_outside_links)
			{
				continue;
			}
			$extra_EID[$EID2] = 1;
			$ppi_links[] = array("EID1" => $EID1, "EID2" => $EID2);
		}
		else
		{
			# when both IDs are in the cluster then we'll get both direction links 
			# and we only need to keep one
			if ($EID1 < $EID2)
			{
				$ppi_links[] = array("EID1" => $EID1, "EID2" => $EID2);
			}
		}	
	}
	$st->close();
	foreach ($extra_EID as $EID => $val)
	{
		$ppi_nodes[$EID] = ensp_name($EID); # no gene name so this will be the label
	}
}
function ppi_script(&$script_code, &$ppi_nodes, &$ppi_links,&$ppi_hugo,&$ens_desc)
{
	global $Node_lbl_type;

	$linked_EID = array();
	foreach ($ppi_links as $data)
	{
		$src =  $data["EID1"];
		$targ =  $data["EID2"];
		$linked_EID[$src] = 1;
		$linked_EID[$targ] = 1;
	}
	$elements = array();
	foreach ($ppi_nodes as $EID => $gname)
	{
		if (isset($linked_EID[$EID]))
		{	
			$hugo = $ppi_hugo[$EID];
			$ensp = ensp_name($EID);
			$msg =  "Ensembl protein:$ensp Gene:$gname";
			if ($gname != $hugo)
			{
				$msg .= " ($hugo)";
			}
			if (isset($ens_desc[$EID]))
			{
				$msg .= "<br>".addslashes($ens_desc[$EID]);
			}
			$elements[] = "{data: {id: '$EID', size:'25px', gene:'$gname',hugo:'$hugo', ensp:'$ensp', msg:'$msg'}}";
		}
	}
	foreach ($ppi_links as $data)
	{
		$src =  $data["EID1"];
		$targ =  $data["EID2"];
		$id = $src."_".$targ;
		$elements[] = "{data: { id:'$id', source: '$src', target: '$targ'}}";
	}
	$script_code = <<<END
$("#cy").html("");
var cy = cytoscape({
  container: document.getElementById('cy'),
autoungrabify: 'true',
 wheelSensitivity: .1,

END;
	$script_code .= "elements: [".implode(",\n",$elements)."],\n";
	$script_code .= <<<END
style:[ 
    {
      selector: 'node',
      style: {
        'background-color': 'red',
        'label': 'data($Node_lbl_type)',
		'font-size' : '10px',
		'width' : 'data(size)',
		'height' : 'data(size)',
		'color' : 'black',
		'title' : 'test'
      }
    },
	{
		selector: '.hugolbl',
		style: {
        	'label': 'data(hugo)'
		}
	},
	{
		selector: '.ensplbl',
		style: {
        	'label': 'data(ensp)'
		}
	},
	{
		selector: '.genelbl',
		style: {
        	'label': 'data(gene)'
		}
	},
    {
      selector: 'edge',
      style: {
        'width': 1,
		'opacity' : '0.5',
        'line-color': 'green',
        'target-arrow-color': 'blue',
        'target-arrow-shape': 'triangle'
      }
    }
  ],
layout:{ name: 'cose'}
});
END;
}
function stringdb_link($nodes)
{
	global $Min_ppi_score;
	$names = array();
	foreach ($nodes as $EID => $val)
	{
		$names[] = ensp_name($EID);
		if (count($names) >= 400)
		{
			break;
		}
	}
	$prot_str = implode("%0D", $names);
	$html = <<<END
<form id="sdbform" action="https://version-10-5.string-db.org/cgi/network.pl" method="get" target="_blank">
<input type="hidden" name="channel1" value="on">
<input type="hidden" name="channel2" value="on">
<input type="hidden" name="channel3" value="on">
<input type="hidden" name="channel4" value="on">
<input type="hidden" name="channel5" value="on">
<input type="hidden" name="channel6" value="on">
<input type="hidden" name="channel7" value="on">
<input type="hidden" name="channel8" value="off">
<input type="hidden" name="block_structure_pics_in_bubbles" value=0>
<input type="hidden" name="direct_neighbor" value=1>
<input type="hidden" name="hide_disconnected_nodes" value="on">
<input type="hidden" name="hide_node_labels" value=0>
<input type="hidden" name="limit" value=0>
<input type="hidden" name="network_display_mode" value="svg">
<input type="hidden" name="network_flavor" value="evidence">
<input type="hidden" name="required_score" value="$Min_ppi_score">
<input type="hidden" name="targetmode" value="proteins">
<input type="hidden" name="species" value="9606">
<input type="hidden" name="identifiers" value="$prot_str">
<a href="#" onclick="document.getElementById('sdbform').submit()" >View at StringDB </a>
</form>
END;
return $html;
}

###############################################################################

function node_lbl_sel($name,$sel)
{
	$vals= array("gene","hugo","ensp");
	$lbls = array("Gene name","HUGO name","Ensemble Protein");
		
	$opts = array();
	foreach ($vals as $idx => $val)
	{
		$lbl = $lbls[$idx];
		$selected = ($sel == $val ? " selected " : "");
		$opts[] = "<option $selected value='$val'>$lbl</option>";
	}
	$html = "<select name='$name' id='$name'>\n".implode("\n",$opts)."\n</select>\n";
	return $html;
}
?>






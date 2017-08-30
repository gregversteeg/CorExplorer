<?php
require_once("db.php");

function ensp_name($num)
{
	# return name of form ENSP00000323929
	while (strlen($num) < 11)
	{
		$num = "0$num";
	}	
	return "ENSP$num";
}
function go_name($num)
{
	# return name of form GO:0000016
	while (strlen($num) < 7)
	{
		$num = "0$num";
	}	
	return "GO:$num";
}
function kegg_name($num)
{
	# return name of form 00161
	while (strlen($num) < 5)
	{
		$num = "0$num";
	}	
	return "$num";
}

#######################################################
#
# Below here functions related to form values
#
#######################################################

function getval($lbl,$default,$required=0)
{
	if (isset($_GET[$lbl]))
	{
		return trim($_GET[$lbl]);
	}
	else if ($required == 1)
	{
		die ("Missing required parameter $lbl\n");
	}
	return trim($default);
}
function checked($val,$def)
{
	if ($val || $def)
	{
		print " checked='true' ";
	}
}
#
# fromForm tells us if the form was actually submitted or the page was called
# with empty query. This only matters if we're trying to make a checkbox
# default to checked. The problem is that when a checkbox is not checked, it
# often puts nothing in the query string, so you can't tell the difference
# between submitted with uncheck, and initial page load with empty query. 
#
function checkbox_val($lbl,$default,$fromForm=1)
{
	if (isset($_GET[$lbl]))
	{
		return ($_GET[$lbl] == "1" || $_GET[$lbl] == "checked" || $_GET[$lbl] == "on" ? 1 : 0);
	}
	if ($fromForm == 1)
	{
		# we cam from the form, but there's no checkbox value, hence it is unchecked
		return 0;
	}
	# we didn't come from the form, hence return default
	return $default;
}
#######################################################
#
# Tooltip text
#
#######################################################

function tip_text($tag)
{
	if ($tag == "multimap")
	{
		return "Whether to show all the Ensembl proteins mapped to a single gene";
	}
	if ($tag == "genelabels")
	{
		return "Whether to label node using gene name or Ensembl protein name";
	}
	if ($tag == "genechoose")
	{
		return "List shows genes and the clusters they are in based on the current setting ".
			" for minimum link weight";
	}
	if ($tag == "cidchoose")
	{
		return "Choose cluster to highlight. If 'redraw' is pressed, then only the parts of the ".
			" graph below that cluster will be drawn";
	}
	if ($tag == "hugo_names")
	{
		return "Use HGNC accepted names, to the extent possible.";
	}
}
#####################################################
#
# Specific form elements
#
###################################################
function run_sel($name,$CRID)
{
	$res = dbq("select ID, lbl from clr order by ID asc");
	while ($r = $res->fetch_assoc())
	{
		$ID = $r["ID"];
		$lbl = $r["lbl"];
		$selected = ($ID == $CRID ? " selected " : "");
		$opts[] = "<option value=$ID $selected>$lbl</option>";
	}
	return "<select name='$name' id='sel_$name'>\n".implode("\n",$opts)."\s</select>\n";
}
function clst_sel($name,$CID,$singlelvl=-1,$defstr="all")
{
	global $CRID;

	$selected = ($CID == 0 ? " selected " : "");
	$opts[] = "<option value='0' $selected>$defstr</option>";

	$lvlwhere = "";
	if ($singlelvl >= 0)
	{
		$lvlwhere = " and clst.lvl=$singlelvl ";
	}

	$res = dbq("select ID, lbl, lvl, count(*) as size from clst ".
		" join g2c on g2c.CID=clst.ID ".
		" where clst.CRID=$CRID $lvlwhere ".
		" group by clst.ID ");
	while ($r = $res->fetch_assoc())
	{
		$ID = $r["ID"];
		$lbl = $r["lbl"];
		$lvl = $r["lvl"] + 1;
		$size = $r["size"] + 1;
		$selected = ($ID == $CID ? " selected " : "");
		$opts[] = "<option value=$ID $selected>Layer$lvl : $lbl ($size genes)</option>";
	}
	# due to the g2c join, the previous only got layer 1
	$res = dbq("select ID, lbl, lvl  from clst ".
		" where clst.CRID=$CRID and lvl > 0 $lvlwhere ".
		" group by clst.ID ");
	while ($r = $res->fetch_assoc())
	{
		$ID = $r["ID"];
		$lbl = $r["lbl"];
		$lvl = $r["lvl"] + 1;
		$selected = ($ID == $CID ? " selected " : "");
		$opts[] = "<option value=$ID $selected>Layer$lvl : $lbl </option>";
	}
	return "<select name='$name' id='sel_$name'>\n".implode("\n",$opts)."\s</select>\n";
}

#####################################################################
#
#	Functions for printing the standard page parts like banners
#
####################################################################

function head_section($title)
{
	echo <<<END
<head>
<title>$title</title>
<meta http-equiv="content-type" content="text/html" charset="utf-8" />
<link rel="stylesheet" type="text/css" href="corex.css"> 
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
</head>

END;
}
##################################################################

function body_start()
{
	echo <<<END
<body>
<div class="outer">
END;
}

$page_opts = array("","over","dset","how","dl","pub");
$page_lbls = array("","Overview","Datasets","How-To","Download","Publications");
$page_pgs = array("welcome.html","overview.html","datasets.html","howto.html",
				"download.html","publications.html");


function header_bar()
{
	global $page_opts, $page_lbls, $page_pgs;
	$cur_opt = $_GET["opt"];

	echo <<<END
<div class="header">
	<table>
		<tr>
			<td valign="middle" style="padding:0px 30px 0px 5px;cursor:pointer" 
				onclick="location.href='/index2.html'">
<span class="logotext" >Cor<span style='color:#cc6666'>Ex</span></span>
			</td>
END;

	# Note that the first "option" is the welcome text 
	# which does not have menu entry
	$numopts = count($page_opts);
	for($i = 1; $i < $numopts; $i++)
	{
		$option = $page_opts[$i];
		$lbl = $page_lbls[$i];
		#$pg = $page_pgs[$i];
		$link = "/index2.html?opt=$option";
		print "<td valign='middle'>\n";
		if ($option == $cur_opt)
		{
			print "<a class='head_selected'>$lbl</a>\n";
		}
		else
		{
			print "<a href='$link' >$lbl</a>\n";
		}
		print "</td>\n";
	}

	echo <<<END
		</tr>
	</table>
</div>
END;

}
function load_content_html()
{
	$opt = $_GET["opt"];
	global $page_opts, $page_lbls, $page_pgs;
	for ($i = 1; $i < count($page_opts); $i++)
	{
		if ($page_opts[$i] == $opt)
		{
			require_once($page_pgs[$i]);
			return;
		}	
	}	
	require_once($page_pgs[0]);
}

##################################################################

function crid_default()
{
	$res = dbq("select min(id) as crid from clr");
	$r = $res->fetch_assoc();
	return $r["crid"];
}
?>

<?php
require_once("util.php");

$CRID = getint("crid",0);
$CID_sel = getint("cid",0);
$type_sel = getval("type","both");
$fdr_thresh = 0.005;
$FromForm = getint("fromform",0); # tell us if it's initial page load or form submit
$FDRsort = checkbox_val("chk_fdr",1,$FromForm);

if (!read_access($CRID))
{
	die("access denied");
}


?>

<head>
<link rel="stylesheet" href="http://www.canvasxpress.org/css/canvasXpress.css" type="text/css"/>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>

</head>
 <body >
<form method="get">
<input type="hidden" name="crid" value="<?php print $CRID ?>">
<input type="hidden" name="ft" value="<?php echo $FT ?>">
<input type="hidden" name="fromform" value="1">
<table width="100%" cellspacing=0 cellpadding=0>
	<tr>
		<td valign="top" align="center">
			<table cellspacing=0 cellpadding=0 >
				<tr>
					<td> Factor: <?php print clst_sel_annot("cid",$CID_sel) ?> </td>
					<td style="padding-left:15px"> <?php print_type_sel($type_sel) ?> </td>
					<td style="padding-left:5px" title="Sort factor menu by best FDR (sort by number if unchecked)">
							FDR sort <input type="checkbox" id="chk_fdr" name="chk_fdr" <?php checked($FDRsort) ?>>
					</td>
				</tr>
			</table>
		</td>
		<!--td valign="top" align="right" style="font-size:1.4em; padding-right:50px;color:#333333" >
			<span id="popout_btn" title="Open in a new page" style="cursor:pointer">&nbsp;&#9654;&nbsp;</span>
		</td-->
	</tr>
</table>
</form>
<script>
$('#sel_cid').change(function() 
{
	$(this).closest('form').submit();	
});
$('#sel_type').change(function() 
{
	$(this).closest('form').submit();	
});
$('#popout_btn').click(function()
{
 	var win = window.open(window.location.href, '_blank');
  	win.focus();
});
$('#chk_fdr').change(function()
{
	$(this).closest('form').submit();	
});
function go_click(term)
{
	parent.postMessage(term,"*");
}
</script>

<?php
if ($CID_sel != 0) 
{  
	if ($type_sel == "go" || $type_sel == "both")
	{
		print "<table rules=all border=true cellpadding=3>\n";
		print "<tr><td><b>GO</b></td><td><b>FDR</b></td><td><b>Description</b></td></tr>\n";	
		$st = dbps("select clst2go.term, clst2go.pval, gos.descr ".
				" from clst2go join gos on gos.term=clst2go.term ".
				" where clst2go.cid=? and gos.CRID=? and pval <= $fdr_thresh order by pval asc");
		$st->bind_param("ii",$CID_sel,$CRID);
		$st->bind_result($term,$pval,$descr);
		$st->execute();
		while ($st->fetch())
		{
			$goname = go_name($term);
			$pval = sprintf("%1.0E",$pval);
			print "<tr><td><a href='javascript:void(0)' onclick='go_click($term)'>$goname</a></td>";
			print "<td style='width:40px'>$pval</td><td>$descr</td></tr>\n";	
		}
		$st->close();
		print "</table>\n";
	}

	if ($type_sel == "kegg" || $type_sel == "both")
	{
		print "<table rules=all border=true cellpadding=3>\n";
		print "<tr><td><b>Kegg</b></td><td><b>FDR</b></td><td><b>Description</b></td></tr>\n";	
		$st = dbps("select clst2kegg.term, clst2kegg.pval, kegg.descr ".
				" from clst2kegg join kegg on kegg.term=clst2kegg.term ".
				" where clst2kegg.cid=? and kegg.CRID=? and pval <= $fdr_thresh order by pval asc");
		$st->bind_param("ii",$CID_sel,$CRID);
		$st->bind_result($term,$pval,$descr);
		$st->execute();
		while ($st->fetch())
		{
			$keggname = kegg_name($term);
			$pval = sprintf("%1.0E",$pval);
			print "<tr><td>$keggname</td><td>$pval</td><td>$descr</td></tr>\n";	
		}
		$st->close();
		print "</table>\n";
	}
}
?>
</body>

<?php

function print_type_sel($sel)
{
	if (!isset($sel) || $sel == "")
	{
		$sel = "both";
	}
	print "<select name='type' id='sel_type'>\n";
	$opts = array("both" => "GO and Kegg", "go" => "GO only", "kegg" => "Kegg only");
	foreach ($opts as $opt => $str)
	{
		$selected = ($opt == $sel ? " selected " : "");
		print "<option $selected value='$opt'>$str</option>\n";
	}
	print "</select>\n";

}
function pval_cmp($a,$b)
{
	if ($a[1] == $b[1])
	{
		return 0;
	}
	return ($a[1] < $b[1]) ? -1 : 1;
}
function clst_sel_annot($name,$CID)
{
	global $CRID, $FDRsort;

	# First get the sort order and best GO 
	$pvals = array();
	$st = dbps("select clst.id,min(clst2go.pval) as pval,clst.lbl  from clst join clst2go on clst2go.cid=clst.id ".
					" where clst.crid=? group by clst2go.cid ");	
	$st->bind_param("i",$CRID);
	$st->bind_result($cid,$pval,$lbl);
	$st->execute();
	while ($st->fetch())
	{
		$pvals[] = array($cid,$pval,$lbl);
	}
	$st->close();
	if ($FDRsort)
	{
		usort($pvals, "pval_cmp");
	}

	$opts = array();
	$selected = ($CID == 0 ? " selected " : "");
	$opts[] = "<option value='0' $selected>--choose--</option>";

	foreach ($pvals as $p)
	{
		$cid = $p[0];
		$pval = $p[1];
		$pval = sprintf("%1.0E",$pval);
		$lbl = $p[2];
		$selected = ($cid == $CID ? " selected " : "");
		$opts[] = "<option value=$cid $selected>$lbl ($pval)</option>";
	}
	return "<select name='$name' id='sel_$name'>\n".implode("\n",$opts)."\s</select>\n";
}



?>

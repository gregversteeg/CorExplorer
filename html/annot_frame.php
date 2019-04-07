<?php
require_once("util.php");

$CRID = getint("crid",0);
$CID_sel = getint("cid",0);
$type_sel = getval("type","both");
$fdr_thresh = 0.005;

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
<input type="hidden" name="ft" value="<?php echo $FT ?>">
<input type="hidden" name="crid" value="<?php print $CRID ?>">
<table width="100%" cellspacing=0 cellpadding=0>
	<tr>
		<td valign="top" align="center">
			<table cellspacing=0 cellpadding=0 >
				<tr>
					<td> Factor: <?php print clst_sel("cid",$CID_sel,0,"--choose--") ?> </td>
					<td style="padding-left:15px"> <?php print_type_sel($type_sel) ?> </td>
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
			print "<tr><td>$goname</td><td>$pval</td><td>$descr</td></tr>\n";	
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



?>

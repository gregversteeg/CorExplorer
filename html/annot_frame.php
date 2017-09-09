<?php
require_once("db.php");
require_once("util.php");

$CRID = getint("crid",0);
$CID_sel = getint("cid",0);

?>

<head>
<link rel="stylesheet" href="http://www.canvasxpress.org/css/canvasXpress.css" type="text/css"/>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>

</head>
 <body >
<form method="get">
<input type="hidden" name="ft" value="<?php echo $FT ?>">
<input type="hidden" name="crid" value="<?php print $CRID ?>">
<table cellpadding=5>
	<tr>
		<td><b>Annotation:</b></td>
		<td> Factor: <?php print clst_sel("cid",$CID_sel,0,"--choose--") ?> </td>
		<td align="right" style="font-size:1.4em; padding-left:50px;color:#333333" >
			<span id="popout_btn" title="Open in a new page" style="cursor:pointer">&nbsp;&#9654;&nbsp;</span>
		</td>
	</tr>
</table>
</form>
<script>
$('#sel_cid').change(function() 
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
	print "<h4>Enriched GO terms:</h4>\n";
	print "<table rules=all border=true cellpadding=3>\n";
	print "<tr><td><b>GO</b></td><td><b>p-value</b></td><td><b>description</b></td></tr>\n";	
	$st = dbps("select clst2go.term, clst2go.pval, gos.descr ".
			" from clst2go join gos on gos.term=clst2go.term ".
			" where clst2go.cid=? and gos.CRID=? order by pval asc");
	$st->bind_param("ii",$CID_sel,$CRID);
	$st->bind_result($term,$pval,$descr);
	$st->execute();
	while ($st->fetch())
	{
		$goname = go_name($term);
		print "<tr><td>$goname</td><td>$pval</td><td>$descr</td></tr>\n";	
	}
	$st->close();
	print "</table>\n";

	print "<h4>Enriched Kegg terms:</h4>\n";
	print "<table rules=all border=true cellpadding=3>\n";
	print "<tr><td><b>Kegg</b></td><td><b>p-value</b></td><td><b>description</b></td></tr>\n";	
	$st = dbps("select clst2kegg.term, clst2kegg.pval, kegg.descr ".
			" from clst2kegg join kegg on kegg.term=clst2kegg.term ".
			" where clst2kegg.cid=? and kegg.CRID=? order by pval asc");
	$st->bind_param("ii",$CID_sel,$CRID);
	$st->bind_result($term,$pval,$descr);
	$st->execute();
	while ($st->fetch())
	{
		$keggname = kegg_name($term);
		print "<tr><td>$keggname</td><td>$pval</td><td>$descr</td></tr>\n";	
	}
	$st->close();
	print "</table>\n";
}

?>
</body>

<?php



?>

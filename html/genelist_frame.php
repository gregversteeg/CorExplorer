<?php
require_once("db.php");
require_once("util.php");

$CRID = getint("crid",0);
$CID_sel = getint("cid",0);
$sort = getval("sort","");

?>

<head>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
</head>
 <body >
<form method="get">
<input type="hidden" name="ft" value="<?php echo $FT ?>">
<input type="hidden" name="crid" value="<?php print $CRID ?>">
<table cellpadding=5>
	<tr>
		<td><b>Gene List:</b></td>
		<td> Factor: <?php print clst_sel("cid",$CID_sel,0,"--choose--") ?> </td>
		<td>
			Sort by: <select name='sort' id='sel_sort' autocomplete='off'>
					<option value='name'>Gene name</option>
					<option value='hugo'>HUGO name</option>
					<option selected value='wt'>Weight</option>
					<option value='mi'>MI</option>
					</select>
		</td>
		<td align="right" style="font-size:1.4em; padding-left:50px;color:#333333" >
			<span id="popout_btn" title="Open in a new page" style="cursor:pointer">&nbsp;&#9654;&nbsp;</span>
		</td>
	</tr>
</table>
</form>
<script>
$(document).ready(function() 
{
	$("#sel_sort").val('<?php echo $sort ?>');
});
$('#sel_cid').change(function() 
{
	$(this).closest('form').submit();	
});
$('#sel_sort').change(function() 
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
	$st = dbps("select lbl from clst where id=?");
	$st->bind_param("i",$CID_sel);
	$st->bind_result($cname);
	$st->execute(); $st->fetch(); $st->close();

	$sortby = "g2c.wt desc";
	if ($sort == "hugo")
	{
		$sortby = "glist.hugo asc";
	}
	else if ($sort == "name")
	{
		$sortby = "glist.lbl asc";
	}
	else if ($sort == "mi")
	{
		$sortby = "g2c.mi desc";
	}

	print "<h4>Factor $cname:</h4>\n";
	print "<table rules=all border=true cellpadding=3>\n";
	print "<tr><td><b>Gene</b></td><td><b>Weight</b></td><td><b>MI</b></td>".
				"<td><b>HUGO</b></td><td><b>description</b></td></tr>\n";	
	$st = dbps("select glist.lbl, glist.hugo, glist.descr, g2c.wt, g2c.mi ".
				"from glist join g2c on g2c.gid=glist.id ".
				" where g2c.cid=?".
				" order by $sortby ");
	$st->bind_param("i",$CID_sel);
	$st->bind_result($gene,$hugo,$descr,$wt,$mi);
	$st->execute();
	while ($st->fetch())
	{
		print "<tr><td>$gene</td><td>$wt</td><td>$mi</td><td>$hugo</td><td>$descr</td></tr>\n";	
	}
	$st->close();
	print "</table>\n";
}
?>

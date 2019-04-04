<?php
require_once("util.php");

$CRID = getint("crid",0);
$CID_sel = getint("cid",0);
$Sort = getval("sort","");

if (!read_access($CRID))
{
	die("access denied");
}
?>

<head>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
</head>
 <body >
<form method="get">
<input type="hidden" name="ft" value="<?php echo $FT ?>">
<input type="hidden" name="crid" value="<?php print $CRID ?>">
<table width="100%" cellspacing=0 cellpadding=0>
	<tr>
		<td valign="top" align="left">
			<table cellspacing=0 cellpadding=0 >
				<tr>
					<td> Factor: <?php print clst_sel("cid",$CID_sel,0,"--choose--") ?> </td>
					<td style="padding-left:25px"> Sort by: <?php sort_sel($Sort) ?> </td>
				</tr>
			</table>
		</td>
		<td valign="top" align="right" style="font-size:1.4em; padding-right:50px;color:#333333" >
			<span id="popout_btn" title="Open in a new page" style="cursor:pointer">&nbsp;&#9654;&nbsp;</span>
		</td>
	</tr>
</table>
</form>
<script>
$(document).ready(function() 
{
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
	if ($Sort == "hugo")
	{
		$sortby = "glist.hugo asc";
	}
	else if ($Sort == "name")
	{
		$sortby = "glist.lbl asc";
	}
	else if ($Sort == "mi")
	{
		$sortby = "g2c.mi desc";
	}

	print "<table rules=all border=true cellpadding=3>\n";
	print "<tr><td><b>Gene</b></td><td><b>Weight/MI</b></td>".
				"<td><b>HUGO</b></td><td><b>description</b></td><td><b>Gene Type</b></td></tr>\n";	
	$st = dbps("select glist.lbl, glist.hugo, glist.descr, glist.gtype, g2c.wt, g2c.mi ".
				"from glist join g2c on g2c.gid=glist.id ".
				" where g2c.cid=?".
				" order by $sortby ");
	$st->bind_param("i",$CID_sel);
	$st->bind_result($gene,$hugo,$descr,$gtype,$wt,$mi);
	$st->execute();
	while ($st->fetch())
	{
		$wt = .01*floor(100*$wt);
		$mi = .01*floor(100*$mi);
		$wtmi = "$wt/$mi";
		print "<tr><td>$gene</td><td>$wtmi</td><td>$hugo</td><td>$descr</td><td>$gtype</td></tr>\n";	
	}
	$st->close();
	print "</table>\n";
}
function sort_sel($sel)
{
	if ($sel == "")
	{
		$sel = "wt";
	}
	$opts = array("name" => "Gene name", "hugo" => "HUGO name", "wt" => "Weight", "mi" => "MI");
	
	print "<select name='sort' id='sel_sort' >\n";
	foreach ($opts as $val => $lbl)
	{
		$selected = ($sel == $val ? " selected " : "");
		print "<option $selected value='$val'>$lbl</option>\n";
	}
	print "</select>\n";

}
?>

<?php
require_once("../util.php");
login_init();
require_login();

print <<<END
<head>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
</head>
END;

check_exec_hide();
print "<h4>CorExplorer Current Projects for User=$USERNAME:</h4>\n";

$gene_counts = array();
$samp_counts = array();

$st = dbps("select glid,count(*) from glist group by glid");
$st->bind_result($glid,$count);
$st->execute();
while ($st->fetch())
{
	$gene_counts[$glid] = $count;
}
$st->close();

$st = dbps("select dsid,count(*) from samp group by dsid");
$st->bind_result($dsid,$count);
$st->execute();
while ($st->fetch())
{
	$samp_counts[$dsid] = $count;
}
$st->close();

$st = dbps("select id,lbl,projstat,dsid,glid,load_dt,hideme from clr");
$st->bind_result($crid,$projname,$projstat,$dsid,$glid,$loaddate,$hidden);
$st->execute();

print "<table border=1 rule=all cellpadding=3>\n";
print "<tr><td>Project</td><td>Status</td><td># Genes</td><td># Samples</td><td>Date</td><td>Hidden</td><td>ID</td><td></td></tr>\n";

while ($st->fetch())
{
	if (!write_access($crid))
	{
		continue;
	}
	$ngenes = (isset($gene_counts[$glid]) ? $gene_counts[$glid] : 0);
	$nsamp = (isset($samp_counts[$dsid]) ? $samp_counts[$dsid] : 0);
	$loglink = "<a href='/manage/log.php?crid=$crid' target='_blank'>view log</a>";
	
	$projlink = "<a href='/explorer.html?crid=$crid' target='_blank'>$projname</a>";
	$checked = ($hidden==1 ? "checked='checked'" : "");
	print "<tr><td>$projlink</td><td>$projstat</td><td>$ngenes</td><td>$nsamp</td><td>$loaddate</td>";
	# For toggling the hidden status we just use a hidden field rather than messing
	# with the cumbersome states of checkboxes
	if (write_access($crid))
	{
		print "<td><form style='padding:0px;margin:0px' ><input type='hidden' name='hide$crid' value=''><input type='checkbox' name='foo' onchange='this.form.submit()' ".
			"$checked style='padding:0px;margin:0px' ></form></td>";
	}
	else
	{
		print "<td><input disabled='true' type='checkbox' name='foo'  $checked style='padding:0px;margin:0px' ></td>";

	}
	print "<td>$crid</td><td>$loglink</td></tr>\n";
}

print "</table>\n";

$st->close();

if (can_load_data())
{
echo <<<END
<h4>New project:</h4>
<form action="add_project.php">
<table cellpadding=3>
	<tr>
		<td>
Name: 
		</td>
		<td>
<input type="text" name="projname" size="25" value=""> (letters,numbers,period,underscore)
		</td>
		<td>
	</tr>
	<tr>
		<td>
Data link: 
		</td>
		<td>
<input type="text" name="datalink" size="45" value=""> (.zip)
		</td>
		<td>
	</tr>
	<tr>
		<td colspan=2 align=left>
<input type="submit" value="Submit" >
		</td>
	</tr>
</table>
</form>
END;
}
else
{
	print "You do not currently have permission to load new projects. <p>\n";
}

$runsel = run_write_sel("crid",0);
dump_projinfo_jscript();
echo <<<END

<h4>Edit Project Info</h4>
<form action="edit_project.php">
<table>
	<tr>
		<td>Project:</td>
		<td>$runsel</td>
	</tr>	
	<tr>
		<td>Name:</td>
		<td><input type="text" name="name" id="edit_name" size="50"></td>
	</tr>
	<tr>
		<td  valign="top">Description:</td>
		<td >
			<textarea name="descr" id="edit_descr" rows=10 cols=80></textarea>
		</td>
	</tr>
	<tr>
		<td colspan=2 align="left">
			<input type="submit" value="Update">
		</td>
	</tr>
</table>
</form>
<script>
$(document).ready(function () {
	crid = $('#sel_crid').val();
	$('#edit_name').val(projinfo[crid]['lbl']);
	$('#edit_descr').val(projinfo[crid]['descr']);
});
$('#sel_crid').change(function() 
{
	crid = $('#sel_crid').val();
	$('#edit_name').val(projinfo[crid]['lbl']);
	$('#edit_descr').val(projinfo[crid]['descr']);
});
</script>
END;

function find_log($pname)
{
	$logfile = "/lfs1/datasets/$pname/load.log";
	if (is_file($logfile))
	{
		return $logfile;
	}
	return "";
}

####################################################################

function dump_projinfo_jscript()
{
	global $ACCESS;
	print "<script>\n";
	print "projinfo = new Array();\n";
	$s = dbps("select id,lbl,descr from clr");
	$s->bind_result($crid,$lbl,$descr);
	$s->execute();
	while ($s->fetch())
	{
		if (!write_access($crid))
		{
			continue;
		}
		$descr = json_encode($descr);
		print <<<END
projinfo[$crid] = new Array();
projinfo[$crid]['lbl'] = '$lbl';
projinfo[$crid]['descr'] = $descr;

END;
	}
	$s->close();
	print "</script>\n";
}
function check_exec_hide()
{
	global $_GET;

	$s = dbps("select id from clr");
	$s->bind_result($crid);
	$s->execute();
	$toggles = array();
	while ($s->fetch())
	{
		$var = "hide$crid";
		if (isset($_GET[$var]))
		{
			$toggles[] = $crid;
		}
	}
	$s->close();
	foreach ($toggles as $crid)
	{
		if (write_access($crid))
		{
			$s = dbps("update clr set hideme=(1-hideme) where id=$crid");
			$s->execute();
			$s->close();
		}
		else
		{
			die("Attempt to alter $crid without access!");
		}
	}
}

?>

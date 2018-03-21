<?php
require_once("../util.php");
login_init();
require_login();
head_section("Manage Projects");

?>
<body>
<table cellspacing=0 cellpadding=0 width="100%">
	<tr>
		<td colspan=2 align=left>
			<table width='100%'  cellpadding=0 cellspacing=0 
					class="graybord" >
				<tr>
					<td width='100%' height='20' align=left colspan=2>
						<table  cellpadding=0 cellspacing=0 width=100% class="graybord" style="background-color:#f5f5f5;">
							<tr>
								<td align=left style="cursor:pointer" onclick="location.href='/'">
									<img src="/logo.png">
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td colspan=2 align=left  style="padding:20px">
<?php 

check_exec_hide();  # if we got here from checking/unchecking one of the boxes in the table

print "<h3>CorExplorer Current Projects for User:$USERNAME</h3>\n";

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

$st = dbps("select id,lbl,projstat,dsid,glid,load_dt,hideme,publc,usrs.usr from clr ".
			" join usrs on usrs.uid=clr.ownedby");
$st->bind_result($crid,$projname,$projstat,$dsid,$glid,$loaddate,$hidden,$public,$uname);
$st->execute();

print "<table border=1 rule=all cellpadding=3>\n";
print "<tr style='font-weight:bold'><td>Project</td><td>Status</td><td># Genes</td><td># Samples</td><td>Date</td>".
		"<td>Hidden</td><td>Public</td><td>ID</td><td>Owner</td><td></td></tr>\n";

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
	$pbchecked = ($public==1 ? "checked='checked'" : "");
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
	if (write_access($crid))
	{
		print "<td><form style='padding:0px;margin:0px' ><input type='hidden' name='pub$crid' value=''>".
			"<input type='checkbox' name='foo' onchange='this.form.submit()' ".
			"$pbchecked style='padding:0px;margin:0px' ></form></td>";
	}
	else
	{
		print "<td><input disabled='true' type='checkbox' name='foo'  $pbchecked style='padding:0px;margin:0px' ></td>";

	}
	print "<td>$crid</td><td>$uname</td><td>$loglink</td></tr>\n";
}

print "</table>\n";

$st->close();

# Block new load if another load is under way. Currently they will interfere. 
$load_inprog = load_in_progress();
$disabledtag = ($load_inprog ? " disabled=true " : "");
$blocktxt = ($load_inprog ? " <span style='color:red'>Another dataset currently loading, please check back later to load a project</span><br> " : "");

if (can_load_data())
{
echo <<<END
<h4>New project:</h4>
$blocktxt 
<!--span style='color:red'>Temporarily unavailable</span><br-->
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
<input type="submit" value="Submit"  $disabledtag>
		</td>
	</tr>
</table>
</form>
END;

$runsel = run_write_sel("crid","crid_edit",0);
$runsel2 = run_write_sel("crid","crid_del",0);
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

<h4>Delete Project</h4>
<form action="web_delete_proj.php">
<table>
	<tr>
		<td>Project:</td>
		<td>$runsel2</td>
	</tr>	
	<tr>
		<td colspan=2 align="left">
			<input type="submit" value="Delete" onclick="return confirm_delete();">
		</td>
	</tr>
</table>
</form>

END;

}
else
{
	print "You do not currently have permission to load or modify projects. <p>\n";
}

echo <<<END

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
function confirm_delete()
{
	proj = $('#crid_del option:selected').html();	
	if (!confirm("Delete project \'" + proj + "\': are you sure?"))
	{
		return false;
	}
	return true;
}
</script>
END;

?>
		</td>
	</tr>
</table>

<?php

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
#
# Respond to hide and/or public checkbox changes
#
function check_exec_hide()
{
	global $_GET;

	$s = dbps("select id from clr");
	$s->bind_result($crid);
	$s->execute();
	$toggles = array();
	$ptoggles = array();
	while ($s->fetch())
	{
		$var = "hide$crid";
		if (isset($_GET[$var]))
		{
			$toggles[] = $crid;
		}
		$var = "pub$crid";
		if (isset($_GET[$var]))
		{
			$ptoggles[] = $crid;
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
	foreach ($ptoggles as $crid)
	{
		if (write_access($crid))
		{
			$s = dbps("update clr set publc=(1-publc) where id=$crid");
			$s->execute();
			$s->close();
		}
		else
		{
			die("Attempt to alter $crid without access!");
		}
	}
}

#
# Run selector for write access operations
#
function run_write_sel($name,$id,$CRID,$def="")
{
	global $ACCESS;
	$st = dbps("select ID, lbl from clr  order by lbl asc");
	$st->bind_result($ID,$lbl);
	$st->execute();
	while ($st->fetch())
	{
		if (write_access($ID))
		{
			$selected = ($ID == $CRID ? " selected " : "");
			$opts[] = "<option value=$ID $selected>$lbl</option>";
		}
	}
	$st->close();
	$html = "<select name='$name' id='$id'>\n";
	if ($def != "")
	{
		$selected = ($CRID==0 ? " selected " : "");
		$html .= "<option $selected value='0'>$def</option>\n";
	}
	$html .= implode("\n",$opts)."\n</select>\n";
	return $html;
}
function load_in_progress()
{
	exec("ps -ef | grep  'load_project' | grep -v 'vi' | grep -v grep", $output);
	if(empty($output))
	{
		return false;
	}
	else
	{
		return true;
	}
}
?>

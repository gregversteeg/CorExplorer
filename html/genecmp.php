<?php
require_once("util.php");

$selected_ids = array();

$Lbltype = $_GET["lbltype"];
if ($Lbltype != "lbl" )
{
	$Lbltype = "hugo";
}

$crid2name = array();
$s = dbps("select id,lbl from clr");
$s->bind_result($crid,$pname);
$s->execute();
while ($s->fetch())
{
	$crid2name[$crid] = $pname;
	if (checkbox_val("ID$crid",0))
	{
		$selected_ids[$crid] = 1;
	}
}
$s->close();


?>

<head>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
</head>

<body>

<?php
if (count($selected_ids) != 0 && count($selected_ids) != 2)
{
	print "<p>Please select two projects<p>";
}
?>

<h3>Compare Gene Lists </h3>
<table>
	<tr>
		<td valign="top">
<?php dump_results() ?>
		</td>
		<td valign="top" style="padding-left:30px">
<?php dump_checkboxes(); ?>
		</td>
	</tr>
</table>
</body>

<?php
function dump_results()
{
	global $selected_ids, $Lbltype;
	if (count($selected_ids) != 2)
	{
		return;
	}
	$gcounts = array();
	$ids = array_keys($selected_ids);
	$crid1 = $ids[0];	
	$crid2 = $ids[1];	
	$pdata = array();
	load_proj_data($pdata,$crid1);
	$pname1 = $pdata["lbl"];	
	load_proj_data($pdata,$crid2);
	$pname2 = $pdata["lbl"];	
	foreach ($ids as $crid)
	{
		$s = dbps("select glist.$Lbltype from glist join clr on glist.glid=clr.glid and clr.id=$crid",1);
		$s->bind_result($name);
		$s->execute();
		while ($s->fetch())
		{
			$name = preg_replace("/\..*/","",$name);
			if (!isset($gcounts[$name]))
			{
				$gcounts[$name] = array();
			}	
			$gcounts[$name][$crid] = 1;
		}
		$s->close();
	}
	$first = array();
	$second = array(); 
	$both = array();
	foreach ($gcounts as $name => $arr)
	{
		if (isset($arr[$crid1]) )
		{
			if (isset($arr[$crid2]))
			{
				$both[] = $name;
			}
			else
			{
				$first[] = $name;
			}
		}	
		else if (isset($arr[$crid2]) )
		{
			$second[] = $name;
		}
		else
		{
			die("Something wrong");
		}
	}
	print ("<p>Shared genes: ".count($both)."<br>");
	print ("$pname1 only: ".count($first)."<br>");
	print ("$pname2 only: ".count($second)."<br>");
	ksort($both); ksort($first); ksort($second);
	print "<p><table rules=all border=true cellpadding=3>\n";
	print "<tr><td>Shared</td><td>$pname1</td><td>$pname2</td></tr>\n";
	for($i = 0; $i < count($gcounts); $i++)
	{
		$g1 = (isset($both[$i]) ? $both[$i] : "");
		$g2 = (isset($first[$i]) ? $first[$i] : "");
		$g3 = (isset($second[$i]) ? $second[$i] : "");
		print "<tr><td>$g1</td><td>$g2</td><td>$g3</td></tr>\n";
	}
	print "</table>\n";
}

function dump_checkboxes()
{
	global $crid2name, $selected_ids;

	echo <<<END
<h4>Pick two:</h4>
<form>
END;
	foreach ($crid2name as $crid => $name)
	{
		$lbl = "ID$crid";
		$checked = (isset($selected_ids[$crid]) ? " checked='checked' " : "");
		echo <<<END
<input type="checkbox" name="$lbl" $checked>&nbsp;$name
<br>
END;
	
	}
	echo <<<END
<p>
<label><input type="radio" name="lbltype" value="lbl" checked="checked">Compare gene names as given</label> 
<label><input type="radio" name="lbltype" value="hugo">Compare common names ("hugo")</label> 
<p>
<input type="submit" value="Submit">
</form>
END;
}


?>

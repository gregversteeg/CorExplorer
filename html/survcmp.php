<?php
require_once("util.php");

$selected_ids = array();

$max_pval = 0.1;

$crid2name = array();
$s = dbps("select id,lbl from clr where hideme=0");
$s->bind_result($crid,$pname);
$s->execute();
while ($s->fetch())
{
	if (checkbox_val("ID$crid",0))
	{
		$selected_ids[$crid] = 1;
		check_read_access($crid);
	}
	if (!read_access($crid))
	{
		continue;
	}
	$crid2name[$crid] = $pname;
}
$s->close();

for($def_id = 0; ; $def_id++)
{
	if (isset($crid2name[$def_id]))
	{
		break;
	}
}

if (count($selected_ids) == 0)
{
	$selected_ids[$def_id] = 1;
}

$vars = array();   # variables = selected runs
$pvals = array();

$max_cnum = 0;
$min_eval = 1;
foreach ($selected_ids as $crid => $foo)
{
	$pvals[$crid] = array();
	$s = dbps("select lbl, -log10(survp) as pval from clst ".
			" where crid=$crid and lvl=0 and survp <= $max_pval order by pval desc");
	$s->bind_result($cnum,$pval);
	$s->execute();
	while ($s->fetch())
	{
		$pvals[$crid][]  = $pval;	
		if ($pval < $min_eval)
		{
			$min_eval = $pval;
		}
	}
	$pname = $crid2name[$crid];
	$vars[] = "\"$pname\"";
	$s->close();
	if (count($pvals[$crid]) > $max_cnum)
	{
		$max_cnum = count($pvals[$crid]);
	}
}

$samps = array();	
for ($n = 0; $n <= $max_cnum; $n++)
{
	$samps[] = $n;
}

$varstr = "[".implode(",\n",$vars)."]";
$sampstr = "[".implode(",\n",$samps)."]";

$datastrs = array();
foreach ($selected_ids as $crid => $foo)
{
	$this_data = array();
	for ($n = 0; $n <= $max_cnum; $n++)
	{
		if (isset($pvals[$crid][$n]))
		{
			$this_data[] = $pvals[$crid][$n];
		}
		else
		{
			$this_data[] = $min_eval;
		}
	}	
	$datastrs[] = "[".implode(",\n",$this_data)."]";
}
$datastr = "[".implode(",\n",$datastrs)."]";

$head_xtra = <<<END
<link rel="stylesheet" href="http://www.canvasxpress.org/css/canvasXpress.css" type="text/css"/>
<script type="text/javascript" src="http://www.canvasxpress.org/js/canvasXpress.min.js"></script>
END;

head_section("Survival Differential Compare", $head_xtra);
body_start();
?>

<h3>Compare Survival Differential (E-value)</h3>
<table>
	<tr>
		<td valign="top">
    		<canvas  id="canvasId" width="900" height="600"></canvas>
		</td>
		<td valign="top" style="padding-left:30px">
<?php dump_checkboxes(); ?>
		</td>
	</tr>
</table>
<script>
var data = {"y": {"vars": <?php echo $varstr ?>,
				  "smps":<?php echo $sampstr ?>,
				  "data": <?php echo $datastr ?>
				 }
			};
var conf = {"graphType": "Line",
			"lineDecoration" : false,
			"smpLabelInterval" : 10,
			"smpTitle" : "Factor",
			"smpLabelRotate" : 90,
			"graphOrientation" : "vertical"
			};                 
var cX = new CanvasXpress("canvasId", data, conf);
</script>

<?php
body_end();

###########################################################################

function dump_checkboxes()
{
	global $crid2name, $selected_ids;

	echo <<<END
<form>
<table>
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
<input type="submit" value="Submit">
</form>
</table>
END;
}


?>

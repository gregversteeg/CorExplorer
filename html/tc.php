<?php
require_once("util.php");

$selected_ids = array();

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
$tc_vals = array();

$max_cnum = 0;
foreach ($selected_ids as $crid => $foo)
{
	$tc_vals[$crid] = array();
	$s = dbps("select lbl, tc from clst where crid=$crid and lvl=0");
	$s->bind_result($cnum,$tc);
	$s->execute();
	while ($s->fetch())
	{
		$tc_vals[$crid][$cnum]  = $tc;	
		if ($cnum > $max_cnum)
		{
			$max_cnum = $cnum;
		}
	}
	$pname = $crid2name[$crid];
	$vars[] = "\"$pname\"";
	$s->close();
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
		if (isset($tc_vals[$crid][$n]))
		{
			$this_data[] = $tc_vals[$crid][$n];
		}
		else
		{
			$this_data[] = 0;
		}
	}	
	$datastrs[] = "[".implode(",\n",$this_data)."]";
}
$datastr = "[".implode(",\n",$datastrs)."]";

?>

<head>
<link rel="stylesheet" href="http://www.canvasxpress.org/css/canvasXpress.css" type="text/css"/>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
</head>

<body>
<script type="text/javascript" src="http://www.canvasxpress.org/js/canvasXpress.min.js"></script>
<h3>Compare Total Correlation</h3>
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
			"graphOrientation" : "vertical"
			};                 
var cX = new CanvasXpress("canvasId", data, conf);
</script>
</body>

<?php

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

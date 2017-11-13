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
$evals = array();

$max_eval = 0;
foreach ($selected_ids as $crid => $foo)
{
	$evals[$crid] = array();
	$s = dbps("select eval, count(*) from (select clst.lbl as cnum, ".
			" max(floor(-log10(ifnull(clst2go.pval,1)))) as eval from clst ".
			" left join clst2go on clst2go.cid=clst.id ".
			" where clst.crid=$crid and lvl=0 group by clst.id ) as tbl ".
			"  group by tbl.eval order by eval desc ");
	$s->bind_result($eval,$count);
	$s->execute();
	while ($s->fetch())
	{
		$evals[$crid][$eval]  = $count;	
		if ($eval > $max_eval)
		{
			$max_eval = $eval;
		}
	}
	$pname = $crid2name[$crid];
	$vars[] = "\"$pname\"";
	$s->close();
}

$samps = array();	
for ($n = 0; $n <= $max_eval; $n++)
{
	$samps[] = $n;
	foreach ($selected_ids as $crid => $foo)
	{
		if (!isset($evals[$crid][$n]))
		{
			$evals[$crid][$n] = 0;
		}
	}
}
foreach ($selected_ids as $crid => $foo)
{
	for ($n = $max_eval-1; $n >= 0; $n--)
	{
		$evals[$crid][$n] += $evals[$crid][$n+1];	
	}
	krsort($evals[$crid]);
}

$varstr = "[".implode(",\n",$vars)."]";
$sampstr = "[".implode(",\n",$samps)."]";

$datastrs = array();
foreach ($selected_ids as $crid => $foo)
{
	$this_data = array();
	for ($n = 0; $n <= $max_eval; $n++)
	{
		if (isset($evals[$crid][$n]))
		{
			$this_data[] = $evals[$crid][$n];
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
<h3>Compare GO Annotation Levels</h3>
<table>
	<tr>
		<td valign="top">
    		<canvas  id="canvasId" width="900" height="700"></canvas>
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
			"smpTitle" : "Annotation E-Value",
			"graphOrientation" : "vertical"
			};                 
var cX = new CanvasXpress("canvasId", data, conf);
</script>
<pre>
<?php #print_r($evals); ?>
</pre>
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

<?php
require_once("db.php");
require_once("util.php");

#
# Receives graph position data and saves
# 
$CRID = getval("crid",0,1);
$JSON = getval("json","",1);

$pdata = array();
load_proj_data($pdata,$CRID);

$pos_x = 0; $pos_y = 0; $ID = 0;

#
# First check the data matches the genes/clusters of the project
#
$gids = array(); 
$cids = array();

$st = dbps("select id from glist where glid=?");
$st->bind_param("i",$pdata["GLID"]);
$st->bind_result($gid);
$st->execute();
while($st->fetch())
{
	$gids[$gid] = 1;
}
$st->close();

$st = dbps("select id from clst where crid=?");
$st->bind_param("i",$CRID);
$st->bind_result($cid);
$st->execute();
while($st->fetch())
{
	$cids[$cid] = 1;
}
$st->close();

$posdata = json_decode($JSON);
$num_genes = 0;
$errmsg = "";
foreach ($posdata as $data)
{
	$idstr = $data->id;
	if (!isset($data->x) || !isset($data->y))
	{
		$errmsg = "Missing pos data for ID:$idstr";
		break;
	}
	$ID = preg_replace("/^[GC]0*/","",$data->id);
	if ($idstr[0] == "C")
	{
		if (!isset($cids[$ID]))
		{
			$errmsg = "Received invalid cluster id $ID";
			break;
		}
	}
	else if ($idstr[0] == "G")
	{
		if (!isset($gids[$ID]))
		{
			$errmsg = "Received invalid gene id $ID";
			break;
		}
		$num_genes++;
	}
	else
	{
		$errmsg = "Received an ID other than gene or cluster";
		break;	
	}
}
if ($errmsg != "")
{
	error_log($errmsg);
	return_fail($errmsg);
}

$msg = "Graph data saved!";
if ($num_genes < count($gids))
{
	$msg .= "\nWARNING: mapped $num_genes genes out of ".count($gids)."!";
}

$stgene = dbps("update glist set pos_x=?, pos_y=? where id=?");
$stgene->bind_param("ddi",$pos_x,$pos_y,$ID);

$stclst = dbps("update clst set pos_x=?, pos_y=? where id=?");
$stclst->bind_param("ddi",$pos_x,$pos_y,$ID);

foreach ($posdata as $data)
{
	$idstr = $data->id;
	$pos_x = $data->x;
	$pos_y = $data->y;
	$ID = preg_replace("/^[GC]0*/","",$data->id);
	if ($idstr[0] == "C")
	{
		$stclst->execute();
	}
	else if ($idstr[0] == "G")
	{
		$stgene->execute();
	}
}
$stgene->close();
$stclst->close();

$st = dbps("update clr set pos_saved=1 where ID=?");
$st->bind_param("i",$CRID);
$st->execute();
$st->close();

return_success($msg);

##############################################

function return_success($msg)
{
	$return_data = array("status" => "success","msg" => $msg);
	header('Content-Type: application/json');
	print json_encode($return_data);
	exit(0);
}
function return_fail($msg)
{
	$return_data = array("status" => "error","msg" => $msg);
	header('Content-Type: application/json');
	print json_encode($return_data);
	exit(0);
}

?>

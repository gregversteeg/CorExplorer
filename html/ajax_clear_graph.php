<?php
require_once("db.php");
require_once("util.php");

#
# Clears saved graph positions
# 
$CRID = getval("crid",0,1);

$pdata = array();
load_proj_data($pdata,$CRID);

$st = dbps("update glist set pos_x=0, pos_y=0 where glid=?");
$st->bind_param("i",$pdata["GLID"]);
$st->execute();
$st->close();

$st = dbps("update clst set pos_x=0, pos_y=0 where crid=?");
$st->bind_param("i",$CRID);
$st->execute();
$st->close();

$st = dbps("update clr set pos_saved=0 where id=?");
$st->bind_param("i",$CRID);
$st->execute();
$st->close();

return_success("Graph positions cleared!");

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

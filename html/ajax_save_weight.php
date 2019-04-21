<?php
require_once("db.php");
require_once("util.php");

#
# Sets default link weight
# 
$CRID = getval("crid",0,1);
$wt = getval("wt",0,1);

$pdata = array();
load_proj_data($pdata,$CRID);

$st = dbps("update clr set def_wt=? where id=?");
$st->bind_param("di",$wt,$CRID);
$st->execute();
$st->close();

return_success("Default weight updated!");

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

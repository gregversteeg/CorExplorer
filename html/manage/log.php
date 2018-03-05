<?php
require_once("../util.php");
require_login();

$DATADIR = $_SERVER["COREXDATADIR"];

$crid = $_GET["crid"];
if (!write_access($crid))
{
	die("access denied");
}
$pinfo = array();
load_proj_data($pinfo,$crid);
$pname = $pinfo["lbl"];
#$path = $pinfo["projdir"]."/load.log";
$path = find_log($pname);
if (!is_file($path))
{
	die("Can't find this log file");
}

$txt = file_get_contents($path);
print "<pre>$txt</pre>";


function find_log($pname)
{
	global $DATADIR;
	$logfile = "$DATADIR/$pname/load.log";
	if (is_file($logfile))
	{
		return $logfile;
	}
	return "";
}

?>

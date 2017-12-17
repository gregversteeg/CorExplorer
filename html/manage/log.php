<?php
require_once("../util.php");
require_login();

$crid = $_GET["crid"];
if (!write_access($crid))
{
	die("access denied");
}
$pinfo = array();
load_proj_data($pinfo,$crid);
$path = $pinfo["projdir"]."/load.log";

$txt = file_get_contents($path);
print "<pre>$txt</pre>";


?>

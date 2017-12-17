<?php
require_once("../util.php");
require_login();


$CRID = $_GET["crid"];
$Newname = $_GET["name"];
$Newdescr = $_GET["descr"];

if (!write_access($CRID))
{
	die("no access");
}

$pdata = array();
load_proj_data($pdata,$CRID);

if (!preg_match("/^[\w\.]+$/",$Newname))
{
	print "Invalid project name: can contain letters, numbers, underscore, periods<br>";
	exit(0);
}

$s = dbps("update clr set lbl=?, descr=? where id=?");
$s->bind_param("ssi",$Newname,$Newdescr,$CRID);
$s->execute();
$s->close();

header('Location: /manage/manage_projects.php');

?>

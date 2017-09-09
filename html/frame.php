<?php
require_once("db.php");
require_once("util.php");

#
# This script provides selectable contents for an iframe in the main panel page. 
# 

$FT = getval("ft","");   # frame type
$FN = getval("fn",0);   # frame number -- only currently matters for graph page
$CRID = getval("crid",0);
$_GET["crid"] = $CRID;

if ($FT == "graph")
{
	require_once("graph_frame.php");
}
else if ($FT == "heatmap")
{
	require_once("heatmap_frame.php");
}
else if ($FT == "ppi")
{
	require_once("ppi_frame.php");
}
else if ($FT == "survival")
{
	require_once("survival_frame.php");
}
else if ($FT == "annotation")
{
	require_once("annot_frame.php");
}
else if ($FT == "genelist")
{
	require_once("genelist_frame.php");
}
else
{
	print "<b>Frame type not specified!</b>";
}





?>

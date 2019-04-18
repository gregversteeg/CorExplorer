<?php
require_once("db.php");
require_once("util.php");

#
# Handles the ajax request for GO-gene and Kegg-gene mappings.
# Given a project ID and GO or Kegg number, returns JSON giving
# 
$CRID = getval("crid",0,1);
$GONUM = getval("gonum",0,1);

$pdata = array();
load_proj_data($pdata,$CRID);

$gids = array();
$st = dbps("select g2e.GID from g2e join e2go on e2go.eterm=g2e.term ".
			"join glist on glist.ID=g2e.GID where e2go.gterm=? and glist.GLID=?");
$st->bind_param("ii",$GONUM,$pdata["GLID"]);
$st->bind_result($gid);
$st->execute();
while ($st->fetch())
{
	$gids[] = $gid;
}
$st->close();

$return_data = array("go" => $GONUM, "gids" => $gids);

header('Content-Type: application/json');
print json_encode($return_data);

?>

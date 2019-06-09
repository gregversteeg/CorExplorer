<?php
require_once("db.php");
require_once("util.php");

#
# Handles the ajax request for additional factor genes (beyond best inclusion)
# 
$GIVEN_CRID = getval("crid",0,1);
$GIVEN_CID = getval("cid",0);

$nodes = array();
$links = array();
#
# Go through the whole gene-to-cluster list in reverse order by weight, keeping the ones
# for this cluster that weren't previous seen for a different cluster. 
# In other words, get the genes in this cluster whose best inclusion is elsewhere.
#
$bestClst = array();
$bestWt = array();
$gid_count = array();    
$link_data = array();
$sql = "select g2c.gid,g2c.cid,g2c.wt,g2c.mi,glist.lbl,glist.hugo,glist.descr,clst.lbl from g2c ".
			" join glist on glist.id=g2c.gid join clst on clst.id=g2c.cid ".
			" where g2c.crid=? order by g2c.wt desc "; 
$st = dbps($sql);
$st->bind_param("i",$GIVEN_CRID);
$st->bind_result($GID,$CID,$wt,$mi,$gname,$hugo,$descr,$clbl);
$st->execute();
while ($st->fetch())
{
	if ($CID == $GIVEN_CID)
	{
		if (isset($gid_count[$GID]))
		{
			$gid_count[$GID]++;
			$bestclst = $bestClst[$GID];
			$bestwt = $bestWt[$GID];
			$msg = ""; //($gname == $hugo ? "gene:$gname" : "gene:$gname ($hugo)");
			//$msg .= "  $descr";
			//$msg .= "<br>Best factor: $bestclst (weight $bestwt)";
			$GIDtag = "G$GID"."_$GIVEN_CID";
			$nodes[] = array("data" => array("id" => $GIDtag, "lbl" => $gname, "cid" => $GIVEN_CID,
							"lvl" => 0, "wt" => $wt, "hugo" => $hugo, "msg" => $msg, "xtra" => 1),
							"position" => array("x" => 0, "y" => 0),
							"classes" => "altlbl nodehide");
			$link_data[$GID] = array("gid" => $GID, "wt" => $wt, "mi" => $mi, "lnum" => $gid_count[$GID]);
		}
		else
		{
			$gid_count[$GID] = 1;
		}
	}
	else
	{
		if (!isset($gid_seen[$GID]))
		{
			$bestClst[$GID] = $clbl;
			$bestWt[$GID] = $wt;
			$gid_count[$GID] = 0;
		}
		$gid_count[$GID]++;
	}
}
$st->close();
#$str = print_r($nodes,true);
#error_log($str);

foreach ($link_data as $GID => $data)
{
	$wt = $data["wt"];
	$mi = $data["mi"];
	$lnum = $data["lnum"];

	$GIDtag = "G$GID"."_$GIVEN_CID";
	calc_link_params($wt,$width,$opacity);

	$wt = sprintf("%.3f",$wt);
	$mi = sprintf("%.3f",$mi);

	$links[] = array("data" => array("target" => "$GIDtag", "source" => "C$GIVEN_CID", 
		"wt" => $wt, "mi" => $mi, "msg" => "weight:$wt,MI:$mi", "width" => "$width", 
		"opacity" => $opacity, "lnum" => $lnum), "classes" => "");
}

#$str = print_r($links,true);
#error_log($str);

$return_data = array("crid" => $GIVEN_CRID, "cid" => $GIVEN_CID, 
			"links" => $links, "nodes" => $nodes);
header('Content-Type: application/json');
print json_encode($return_data);

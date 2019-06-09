<?php
require_once("db.php");
require_once("util.php");

$numSizeBins = 5;		# edge size bins for edge width based on weight
						# This calc duplicated in graph_frame
#
# Handles the ajax request for additional graph links (beyond best inclusion)
# 
$GIVEN_CRID = getval("crid",0,1);
$GIVEN_CID = getval("cid",0);
$GIVEN_LVL = getval("lvl",0);

$cidlist = array();
if ($GIVEN_LVL == 2)
{
	# We need to get all the level 1 clusters whose best inclusion is in the level 2
	$cidseen = array();
	$st = dbps("select cid1,cid2 from c2c where crid=? order by wt desc");
	$st->bind_param("i",$GIVEN_CRID);
	$st->bind_result($CID1,$CID2);
	$st->execute();
	while ($st->fetch())
	{
		if (!isset($cidseen[$CID1]))
		{
			$cidseen[$CID1] = 0;
			if ($CID2 == $GIVEN_CID)
			{
				$cidlist[$CID1] = 1;
			}
		}
	}
	$st->close();
}
else if ($GIVEN_LVL == 1)
{
	$cidlist[$GIVEN_CID] = 1;
}
#
# First we will get the best-inc gene list for these cluster(s) 
# Then go through all of the gene links and pick the ones we want. 
# It seems faster than using inlink/outlink queries.
#
$gid_seen = array();    
$clst_genes = array();
$maxwt = 0;
$sql = "select gid,cid,wt from g2c where crid=? order by wt desc "; 
$st = $DB->prepare($sql);
$st->bind_param("i",$GIVEN_CRID);
$st->bind_result($GID,$CID,$wt);
$st->execute();
while ($st->fetch())
{
	if ($maxwt == 0)
	{
		$maxwt = $wt;
	}
	if (!isset($gid_seen[$GID]))
	{
		if (isset($cidlist[$CID]))
		{
			$clst_genes[$GID] = 1;
		}
		$gid_seen[$GID] = 1;
	}
}
$st->close();

$sql = "select GID, CID, mi,wt from g2c where crid=?  order by wt desc ";  
$st = $DB->prepare($sql);
$st->bind_param("i",$GIVEN_CRID);
$st->bind_result($GID,$CID,$mi,$wt);
$st->execute();

$gid_seen = array();    
$links = array();
while ($st->fetch())
{
	if (!isset($gid_seen[$GID]))
	{
		$gid_seen[$GID] = 0;
	}
	$gid_seen[$GID]++;

	if ($CID != $GIVEN_CID && !isset($clst_genes[$GID]))
	{
		continue;
	}

	$CIDtag = "C$CID";
	$GIDtag = "G$GID";


	if ($gid_seen[$GID] > 1)
	{
		calc_link_params($wt,$width,$opacity);

		$wt = sprintf("%.3f",$wt);
		$mi = sprintf("%.3f",$mi);

		$links[] = array("data" => array("target" => "$GIDtag", "source" => "$CIDtag", 
			"wt" => $wt, "mi" => $mi, "msg" => "weight:$wt,MI:$mi", "width" => "$width", 
			"opacity" => $opacity, "lnum" => $gid_seen[$GID]), "classes" => "");
	}
}
$st->close();

$str = print_r($links,true);
#error_log($str);

$return_data = array("crid" => $GIVEN_CRID, "cid" => $GIVEN_CID, 
			"links" => $links, "cids" => $cidlist);
header('Content-Type: application/json');
print json_encode($return_data);

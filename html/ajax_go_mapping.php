<?php
require_once("db.php");
require_once("util.php");

#
# Handles the ajax request for GO-gene and GO/Kegg-cluster mappings.
# 
$CRID = getval("crid",0,1);
$GONUM = getval("gonum",0);
$KEGGNUM = getval("keggnum",0);

$go_enrich_pval = 0.005;
$kegg_enrich_pval = 0.005;

$pdata = array();
load_proj_data($pdata,$CRID);

$gids = array();
$cids = array();

if ($GONUM > 0)
{
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

	$st = dbps("select cid from clst2go join clst on clst.ID=clst2go.CID ".
				" where clst.CRID=? and clst2go.pval <= ? ".
				" and clst2go.term =? ");
	$st->bind_param("idi",$CRID,$go_enrich_pval,$GONUM);
	$st->bind_result($cid);
	$st->execute();
	while ($st->fetch())
	{
		$cids[] = $cid;
	}
	$st->close();
}
else if ($KEGGNUM > 0)
{
	$st = dbps("select cid from clst2kegg join clst on clst.ID=clst2kegg.CID ".
				" where clst.CRID=? and clst2kegg.pval <= ? ".
				" and clst2kegg.term =? ",1);
	$st->bind_param("idi",$CRID,$kegg_enrich_pval,$KEGGNUM);
	$st->bind_result($cid);
	$st->execute();
	while ($st->fetch())
	{
		$cids[] = $cid;
	}
	$st->close();

}
else
{
	die("ajax_go_mapping called with no data");
}

$cidstr = implode(",",$cids);

$return_data = array("go" => $GONUM, "kegg" => $KEGGNUM, "gids" => $gids, "cids" => $cids);

header('Content-Type: application/json');
print json_encode($return_data);

?>

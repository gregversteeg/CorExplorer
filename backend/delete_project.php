<?php
require_once("util.php");

$projname = $argv[1];

$st = dbps("select id,dsid,glid from clr where lbl='$projname'");
$st->bind_result($CRID,$DSID,$GLID);
$st->execute();
if (!$st->fetch())
{
	die("$projname not found\n");
}
$st->close();

yesno("Delete project $projname (CRID=$CRID)");

print "Deleting $projname\n";

if ($CRID != 0)
{
	clear_expr($CRID);
}
if ($CRID != 0)
{
	dbq("delete from clr where ID=$CRID");
	dbq("delete from survdt_ov where CRID=$CRID");
	dbq("delete clst_pair.* from clst_pair, clst where clst_pair.CID1=clst.ID and clst.crid=$CRID");
	dbq("delete pair_survdt.* from pair_survdt, clst where pair_survdt.CID1=clst.ID and clst.crid=$CRID");
	dbq("delete pair_lbls.* from pair_lbls, clst where pair_lbls.CID1=clst.ID and clst.crid=$CRID");
	dbq("delete clst2go.* from clst2go, clst where clst.crid=$CRID and clst2go.cid=clst.id");
	dbq("delete clst2kegg.* from clst2kegg, clst where clst.crid=$CRID and clst2kegg.cid=clst.id");
	dbq("delete lbls.* from lbls, clst where clst.crid=$CRID and lbls.cid=clst.id");
	dbq("delete survdt.* from survdt, clst where clst.crid=$CRID and survdt.cid=clst.id");
	$tables = array("clst","g2c","c2c","gos","kegg");
	delete_from_tables($tables,"CRID",$CRID);
}
if ($DSID != 0)
{
	dbq("delete sampdt.* from sampdt, samp where sampdt.sid=samp.id and samp.dsid=$DSID");
	dbq("delete sampalias.* from sampalias, samp where sampalias.sid=samp.id and samp.dsid=$DSID");
	dbq("delete from dset where ID=$DSID");
	$tables = array("samp","expr","clr");
	delete_from_tables($tables,"DSID",$DSID);
}
if ($GLID != 0)
{
	dbq("delete g2e.* from g2e, glist where glist.glid=$GLID and g2e.gid=glist.id");
	dbq("delete from glists where ID=$GLID");
	$tables = array("glist");
	delete_from_tables($tables,"GLID",$GLID);
}
#############################################

function delete_from_tables(&$tables,$key,$val)
{
	foreach ($tables as $table)
	{
		print "$table\n";
		dbq("delete from $table where $key = $val");
	}
}
?>

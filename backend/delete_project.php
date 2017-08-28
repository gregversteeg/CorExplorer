<?php
require_once("db.php");

$CRID = 3;
$DSID = 2;
$GLID = 2;

if ($DSID != 0)
{
	print "Clear expression table for DSID=$DSID...slow\n";
	dbq("delete from expr where DSID=$DSID");
}
if ($CRID != 0)
{
	dbq("delete from clr where ID=$CRID");
	$tables = array("clst","g2c","c2c","gos","kegg");
	delete_from_tables($tables,"CRID",$CRID);
}
if ($DSID != 0)
{
	dbq("delete from dset where ID=$DSID");
	$tables = array("samp","expr","clr");
	delete_from_tables($tables,"DSID",$DSID);
}
if ($GLID != 0)
{
	dbq("delete from glists where ID=$GLID");
	$tables = array("glist");
	delete_from_tables($tables,"GLID",$GLID);
}
#############################################

# incremental clear of expr table, which MAY be faster
# than clearing all at once using DSID
# UNFORTUNATELY, NOT FASTER!!

function clear_expr_by_DS($DSID)
{
	print "Clearing expr for DSID=$DSID\n";
	$samps = array();
	$res = dbq("select ID from samp where DSID=$DSID");
	while ($r = $res->fetch_assoc())
	{
		$samps[] = $r["ID"];
	}
	foreach ($samps as $SID)
	{
		print "$SID      \r";
		dbq("delete from expr where DSID=$DSID and SID=$SID");
	}
}
function delete_from_tables(&$tables,$key,$val)
{
	foreach ($tables as $table)
	{
		print "$table\n";
		dbq("delete from $table where $key = $val");
	}
}
?>

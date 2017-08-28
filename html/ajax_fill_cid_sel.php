<?php
require_once("db.php");
require_once("util.php");

$CRID = getval("crid",0); 
if ($CRID == 0)
{
	exit();
}

$opts[] = "<option value='0' selected>all</option>";
$res = dbq("select ID, lbl, lvl, count(*) as size from clst ".
	" join g2c on g2c.CID=clst.ID ".
	" where clst.CRID=$CRID  ".
	" group by clst.ID ");
while ($r = $res->fetch_assoc())
{
	$ID = $r["ID"];
	$lbl = $r["lbl"];
	$lvl = $r["lvl"] + 1;
	$size = $r["size"] + 1;
	$opts[] = "<option value=$ID >Layer$lvl : $lbl ($size genes)</option>";
}
# due to the g2c join, the previous only got layer 1
$res = dbq("select ID, lbl, lvl  from clst ".
	" where clst.CRID=$CRID and lvl > 0  ".
	" group by clst.ID ");
while ($r = $res->fetch_assoc())
{
	$ID = $r["ID"];
	$lbl = $r["lbl"];
	$lvl = $r["lvl"] + 1;
	$opts[] = "<option value=$ID >Layer$lvl : $lbl </option>";
}
print "<pre>".implode("\n",$opts)."</pre>";


?>

<?php
require_once("util.php");
$CRID = getint("crid",0);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="genelist.tsv"');

print "Factor\tGene\tHugoName\tWeight\n";
$st = dbps("select clst.lbl, glist.lbl, glist.hugo, g2c.wt from ".
			"g2c join clst on clst.id=g2c.cid join glist on glist.id=g2c.gid ".
			"where clst.crid=$CRID and clst.lvl=0 order by clst.id asc, g2c.wt desc ");
$st->bind_result($clbl,$glbl,$hugo,$wt);
$st->execute();
while ($st->fetch())
{
	print "$clbl\t$glbl\t$hugo\t$wt\n";
}
$st->close();

?>

<?php

require_once("db.php");

$res = dbq("show engine innodb status");
print_r($res->fetch_assoc());
#while ($r = $res->fetch_assoc())
#{
#	$lbl = $r["lbl"];
#	print "$lbl\n";	
#}



?>

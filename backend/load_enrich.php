<?php
require_once("db.php");

#$CRID = 1;
#$annos_dir = "/local/wnelson/disk/jul_2015_ExpressionData/Code/annotations/new_annos2";

$CRID = 4;
$annos_dir = "/local/wnelson/disk/bLUAD/lung/annos";

$thresh = 0.05;

print "Deleting gos/kegg for CRID=$CRID\n";
dbq("delete from gos where CRID=$CRID");
dbq("delete from kegg where CRID=$CRID");

$gos_seen = array();
$kegg_seen = array();

$res = dbq("select term from gos");
while ($r = $res->fetch_assoc())
{
	$gos_seen[$r["term"]] = 1;
}
$res = dbq("select term from kegg");
while ($r = $res->fetch_assoc())
{
	$kegg_seen[$r["term"]] = 1;
}

$cid2num = array();
$res = dbq("select ID,lbl from clst where CRID=$CRID and lvl=0");
while ($r = $res->fetch_assoc())
{
	$CID = $r["ID"];
	$cnum = $r["lbl"];
	$cid2num[$CID] = $cnum;
	dbq("delete from clst2go where CID=$CID");
	dbq("delete from clst2kegg where CID=$CID");
}

#
# For GO and Kegg terms we use prepared statements due to the desc fields.
# These are grouped into a transaction for each group file. 
# For the clst2go and clst2kegg mappings we use regular inserts and
# group them manually for speed. 
#

foreach ($cid2num as $CID => $grp)
{
	$GOfile = "$annos_dir/$grp.GO.txt";
	$Keggfile = "$annos_dir/$grp.KEGG.txt";
	if (!is_file($GOfile))
	{
		print("Warning: $GOfile not found\n");
	}
	else
	{
		print "$GOfile                       \n";
		$garray = array(); # arrays to group the values to be added
		$carray = array(); 
		$s1 = $DB->prepare("insert into gos (term,descr,CRID) values(?,?,$CRID)");
		$s1->bind_param("is",$term,$desc);	
		dbq("start transaction");
		$fh = fopen($GOfile,"r");
		{
			$line = fgets($fh);
			while (($line = fgets($fh)) != false) 
			{
				$fields = preg_split('/\t/', $line);
				$go 	= trim($fields[0]);
				$fdr 	= trim($fields[4]);
				$desc 	= trim($fields[5]);

				if ($fdr > $thresh)
				{
					continue;
				}
				
				$term = preg_replace('/GO:0*/','',$go);
				if (!is_numeric($term))
				{
					die("bad term:$term for $go in $GOfile\n");
				}
				if (!is_numeric($fdr))
				{
					die("bad FDR:$fdr for $go in $GOfile\n");
				}

				if (!isset($gos_seen[$term]))
				{
					$s1->execute();
					$gos_seen[$term] = 1;
				}
				$carray[] = "($CID,$term,$fdr)";
			}
		}
		fclose($fh);
		dbq("commit");
		if (count($carray) > 0)
		{
			$vals = implode(",",$carray);
			$qry = "insert into clst2go (CID,term,pval) values$vals";
			#print "$qry\n";
			dbq($qry);
		}
	}
	if (!is_file($Keggfile))
	{
		print("Warning: $Keggfile not found\n");
	}
	else
	{
		print "$Keggfile                       \n";
		$garray = array(); 

		$s1 = $DB->prepare("insert into kegg (term,descr,CRID) values(?,?,$CRID)");
		$s1->bind_param("is",$term,$desc);	
		dbq("start transaction");

		$fh = fopen($Keggfile,"r");
		{
			$line = fgets($fh);
			while (($line = fgets($fh)) != false) 
			{
				$fields = preg_split('/\t/', $line);
				$kegg	= trim($fields[0]);
				$fdr 	= trim($fields[4]);
				$desc 	= trim($fields[5]);

				if ($fdr > $thresh)
				{
					continue;
				}
				
				$term = preg_replace('/^0*/','',$kegg);
				if (!is_numeric($term))
				{
					die("bad term:$term for $kegg in $Keggfile\n");
				}
				if (!is_numeric($fdr))
				{
					die("bad FDR:$fdr for $kegg in $Keggfile\n");
				}

				if (!isset($kegg_seen[$term]))
				{
					$s1->execute();
					$kegg_seen[$term] = 1;
				}
				$carray[] = "($CID,$term,$fdr)";
			}
		}
		fclose($fh);
		dbq("commit");
		if (count($carray) > 0)
		{
			$vals = implode(",",$carray);
			$qry = "insert into clst2kegg (CID,term,pval) values$vals";
			#print "$qry\n";
			dbq($qry);
		}
	}
}


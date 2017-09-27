<?php
require_once("util.php");

$enrich_thresh = 0.05;
$minMI = .002;

$projname = $argv[1];
$annodir = $argv[2];

$CRID = find_proj($projname);;
load_enrich();
exit(0);

system("rm $annodir/*");

make_group_files();

print("Begin enrichment calls to StringDB\n");
system("./stringdb_annos.R $annodir");

load_enrich();

function make_group_files()
{
	print "Make group list files\n";
	global $annodir,$CRID,$minMI;
	$cids = array();
	$res = dbq("select ID,lbl from clst where CRID=$CRID and lvl=0");
	while ($r = $res->fetch_assoc())
	{
		$cid = $r["ID"];
		$lbl = $r["lbl"];
		$cids[$lbl] = $cid;
	}

	$numFiles = 0;
	foreach ($cids as $lbl => $cid)
	{
		$grpfile = "$annodir/group$lbl.txt";
		$names = array();
		$res = dbq("select distinct term from g2e join g2c on g2c.GID=g2e.GID ".
				" where g2c.CID=$cid and g2c.mi >= $minMI order by g2c.mi desc");
		while ($r = $res->fetch_assoc())
		{
			$term = $r["term"];
			$names[] = ensp_name($term);
		}
		if (count($names) > 0)
		{
			print "$grpfile                            \r";
			$numFiles++;
			file_put_contents($grpfile,implode("\n",$names)."\n");
		}
	}
	print "$numFiles created                                 \n";
}
function ensp_name($num)
{
	# return name of form ENSP00000323929
	while (strlen($num) < 11)
	{
		$num = "0$num";
	}	
	return "ENSP$num";
}
function load_enrich()
{
	global $annodir,$CRID,$enrich_thresh;

	print "Load enrichment results\n";

	print "Deleting gos/kegg for CRID=$CRID\n";
	dbq("delete from gos where CRID=$CRID");
	dbq("delete from kegg where CRID=$CRID");

	$gos_seen = array();
	$kegg_seen = array();

	$res = dbq("select term from gos where crid=$CRID");
	while ($r = $res->fetch_assoc())
	{
		$gos_seen[$r["term"]] = 1;
	}
	$res = dbq("select term from kegg where crid=$CRID");
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
	# For the clst2go and clst2kegg mappings we use regular inserts and
	# group them manually for speed. 
	#

	foreach ($cid2num as $CID => $grp)
	{
		$GOfile = "$annodir/$grp.GO.txt";
		$Keggfile = "$annodir/$grp.KEGG.txt";
		if (!is_file($GOfile))
		{
			print("Warning: $GOfile not found\n");
		}
		else
		{
			print "$GOfile                       \n";
			$garray = array(); # arrays to group the values to be added
			$carray = array(); 
			$st = dbps("insert into gos (term,descr,CRID) values(?,?,$CRID)");
			$st->bind_param("is",$term,$desc);	
			$fh = fopen($GOfile,"r");
			{
				$line = fgets($fh);
				while (($line = fgets($fh)) != false) 
				{
					$fields = preg_split('/\t/', $line);
					$go 	= trim($fields[0]);
					$fdr 	= trim($fields[4]);
					$desc 	= trim($fields[5]);

					if ($fdr > $enrich_thresh)
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
						$st->execute();
						$gos_seen[$term] = 1;
					}
					$carray[] = "($CID,$term,$fdr)";
				}
			}
			fclose($fh);
			$st->close();
			if (count($carray) > 0)
			{
				$vals = implode(",",$carray);
				$qry = "insert into clst2go (CID,term,pval) values$vals";
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

			$st = dbps("insert into kegg (term,descr,CRID) values(?,?,$CRID)");
			$st->bind_param("is",$term,$desc);	

			$fh = fopen($Keggfile,"r");
			{
				$line = fgets($fh);
				while (($line = fgets($fh)) != false) 
				{
					$fields = preg_split('/\t/', $line);
					$kegg	= trim($fields[0]);
					$fdr 	= trim($fields[4]);
					$desc 	= trim($fields[5]);

					if ($fdr > $enrich_thresh)
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
						$st->execute();
						$kegg_seen[$term] = 1;
					}
					$carray[] = "($CID,$term,$fdr)";
				}
			}
			fclose($fh);
			$st->close();
			if (count($carray) > 0)
			{
				$vals = implode(",",$carray);
				$qry = "insert into clst2kegg (CID,term,pval) values$vals";
				#print "$qry\n";
				dbq($qry);
			}
		}
	}
}

?>

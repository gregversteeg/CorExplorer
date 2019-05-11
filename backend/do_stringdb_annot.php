<?php
require_once("util.php");
$Rscript_dir = "$SCRIPTDIR/Rscripts";

$enrich_thresh = 0.05;
$minMI = .002;

$projname = $argv[1];
$annodir = $argv[2];

$pdata = array();
load_proj_data2($pdata,$projname);
$CRID = $pdata["ID"];
$GLID = $pdata["GLID"];

if (!is_dir($annodir))
{
	mkdir($annodir);
}
run_cmd("rm $annodir/*",$retval);

make_group_files();

print("Begin enrichment calls to StringDB\n");
run_cmd("$Rscript_dir/stringdb_annos.R $annodir",$retval);

load_enrich();
load_ensp_maps();


#########################################################################################

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
		print "$grpfile                            \n";
		$names = array();
		#
		# Lists use hugo names because String doesn't process ENSG. 
		# However, shrinkage2 used glist.lbl instead of glist.hugo
		#
		$res = dbq("select hugo as name from glist join g2c on g2c.GID=glist.ID ".
				" where g2c.CID=$cid and g2c.mi >= $minMI order by g2c.mi desc");
		$seen = array();
		while ($r = $res->fetch_assoc())
		{
			$name = preg_replace("/\..*/","",$r["name"]); # String can't handle .N
			if (isset($seen[$name]))
			{
				continue;
			}
			$seen[$name] = 1;
			$names[] = $name;
		}
		if (count($names) > 0)
		{
			$numFiles++;
			file_put_contents($grpfile,implode("\n",$names)."\n");
		}
		else
		{
			print "Factor $lbl: empty group file!\n";
		}
	}
	print "$numFiles created                                 \n";
}

#################################################################################

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

#################################################################################

function load_ensp_maps()
{
	global $annodir, $GLID;

	print "Upload ENSP mapping...\n";

	$hugo2term = array();
	#
	# Even though stringdb.map is called with takeFirst=TRUE, we get a few
	# multi-maps due to separate calls for different factors...I guess that explains it.
	# StringDB algorithm for mapping is not deterministic. 
	# DB can handle one-to-multiple mappings. 
	$doubles = array();

	$files = glob("$annodir/*.map.txt");
	foreach ($files as $file)
	{
		$fh = fopen($file,"r");
		fgets($fh);
		while (($line = fgets($fh)))
		{
			$fields = preg_split("/\s+/",trim($line));
			if (count($fields) != 3)
			{
				print_r($fields);
				die ("$file\nbad line\n$line");
			}
			$hugo = strtolower(preg_replace("/\"/","",$fields[1]));
			$ensg = preg_replace("/\"/","",$fields[2]);
			$eterm = preg_replace("/^.*\.ENSP0+/","",$ensg);
			if (!isset($hugo2term[$hugo]))
			{
				$hugo2term[$hugo] = array();
			}
			$hugo2term[$hugo][$eterm] = 1;
		}
	}


	# get the gene IDs for each hugo; note may not always be unique
	$hugo_map = array();
	$s = dbps("select id,hugo from glist where glist.glid=$GLID");
	$s->bind_result($gid,$hugo);
	$s->execute();
	while ($s->fetch())
	{
		$hugo = strtolower($hugo);
		strip_gene_suffix($hugo);
		if (!isset($hugo_map[$hugo]))
		{
			$hugo_map[$hugo] = array();
		}
		$hugo_map[$hugo][] = $gid;
	}
	dbq("delete g2e.* from g2e, glist where g2e.gid=glist.id and glist.glid=$GLID");
	foreach ($hugo2term as $hugo => $terms)
	{
		if (isset($hugo_map[$hugo]))
		{
			foreach ($hugo_map[$hugo] as $gid)
			{
				foreach ($terms as $term => $foo)
				{
					dbq("insert into g2e (gid,term)	values($gid,$term)");
				}
			}
		}
		else
		{
			print "Unknown $hugo!\n";
		}
	}


}

?>

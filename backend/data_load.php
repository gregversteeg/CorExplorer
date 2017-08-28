<?php
require_once("util.php");
# Load lung data

require_once("db.php");
$time_start = time();

$corex_datadir = "/local/wnelson/disk/bLUAD/lung/text_files";
$matrix_file = "/local/wnelson/disk/bLUAD/LUAD_FPKM-UQ.csv";
$gene_file = "$corex_datadir/genelist.txt";  # this is just first row of any weights file
$sample_file = "$corex_datadir/samples.txt"; # first column of labels file
$dataset = "LUAD2";
$run = "lung2";
$run_method = "corex";
$expr_type = "fpkm";
$weights_dir = $corex_datadir;
$wtfile_pfx = "$weights_dir/weights_layer";
$mifile_pfx = "$weights_dir/mis_layer";
$wtfile_sfx = ".csv";

$DSID = 3;
$GLID = 3;
$CRID = 4;

if ($DSID == 0)
{
	dbps("insert into dset (lbl,expr_type) values(?,?)", 
			array($dataset,$expr_type),"ss");
	$DSID = dblastid("dset","ID");
	print "new DSID: $DSID\n";
}
if ($GLID == 0)
{
	dbps("insert into glists (descr) values(?)", array($dataset),"s");
	$GLID = dblastid("glists","ID");
	print "new GLID: $GLID\n";
}
if ($CRID == 0)
{
	dbps("insert into clr (lbl,meth,GLID,DSID) values(?,?,?,?)", 
			array($run,$run_method,$GLID,$DSID),"ssii");
	$CRID = dblastid("clr","ID");
	print "new CRID: $CRID\n";
}
#
# Load the genes 
#

$genes = trim(file_get_contents($gene_file));
$garray = preg_split('/[,\s]+/', $genes);

# check the names out first
$gene_seen = array();
foreach ($garray as $gene)
{
	$gene = trim($gene);	
	if (isset($gene_seen[$gene]))
	{
		die ("Duplicate gene name: $gene\n");
	}
	$gene_seen[$gene] = 1;
	if (preg_match('/[^\w]/',$gene))
	{
		die ("bad gene name:$gene\n");
	}
}
print count($gene_seen)." genes ready to load...\n";

$num_loaded = 0;
foreach ($garray as $gene)
{
	$gene = trim($gene);	
	if (!entry_exists2("glist","lbl",$gene,"GLID",$GLID))
	{
		dbq("insert into glist (GLID,lbl) values($GLID,'$gene')");
		$GID = dblastid("glist","ID");
		$gene2ID[$gene] = $GID;
		#print "$gene\t$GID\n";
		$num_loaded++;
	}
}
print "Loaded $num_loaded genes\n";

#
# Load the samples
#

$samples = trim(file_get_contents($sample_file));
$sarray = preg_split('/[,\s]+/', $samples);
$num_loaded = 0;
$sampsSeen = array();

# check the names out first
$sampsSeen = array();
foreach ($sarray as $samp)
{
	$samp = trim($samp);	
	if (isset($sampsSeen[$samp]))
	{
		die ("Duplicate sample name: $samp\n");
	}
	$sampsSeen[$samp] = 1;
	if (preg_match('/[^\w-]/',$samp))
	{
		die ("bad sample name:$samp\n");
	}
}
print count($sampsSeen)." samples ready to load...\n";

foreach ($sarray as $samp)
{
	$samp = trim($samp);	
	if (!entry_exists2("samp","lbl",$samp,"DSID",$DSID))
	{
		dbq("insert into samp (lbl,DSID) values('$samp',$DSID)");
		$SID = dblastid("samp","ID");
		#print "$samp\t$SID\n";
		$num_loaded++;
	}
}
print "Loaded $num_loaded new samples\n";


#
# Get gene and sample IDs
#

$gene2ID = array();
$res = dbq("select lbl, ID from glist where GLID=$GLID");
while ($r = $res->fetch_assoc())
{
	$gene2ID[$r["lbl"]] = $r["ID"];
}
$samp2ID = array();
$res = dbq("select lbl, ID from samp where DSID=$DSID");
while ($r = $res->fetch_assoc())
{
	$samp2ID[$r["lbl"]] = $r["ID"];
}
$numSamp = count($samp2ID);
$numGene = count($gene2ID);
print "$numGene genes loaded from DB\n";
print "$numSamp samples loaded from DB\n";

print "clear out CRID=$CRID\n";
dbq("delete from g2c where CRID=$CRID");
dbq("delete from c2c where CRID=$CRID");
dbq("delete from clst where CRID=$CRID");

print "##############################################\n";
print "Loading level 0 weights and MI\n";

$lvl = 0;
$wtfile = $wtfile_pfx.$lvl.$wtfile_sfx;
$mifile = $mifile_pfx.$lvl.$wtfile_sfx;
if (!is_file($wtfile)) 
{
	die ("No layer0 weight file ($wtfile)!\n");
}
if (!is_file($mifile)) 
{
	die ("No layer0 MI file ($mifile)!\n");
}

$wts = array();
$mis = array();
read_matrix($wts,$nRows,$nCols,$wtfile);
$numFacts = $nRows-1;
if ($nCols != $numGene + 1)
{
	die ("wt file has $nCols columns!\n");
}
print "$numFacts new factors\n";
read_matrix($mis,$nRows,$nCols,$mifile);
if ($nCols != $numGene + 1)
{
	die ("mi file has $nCols!\n");
}
if ($nRows != $numFacts + 1)
{
	die ("mi file has $nRows rows!\n");
}

$col2GID = array();
for ($c = 1; $c < $nCols; $c++)
{
	$gene = $wts[0][$c];
	if ($gene != $mis[0][$c])
	{
		die ("mismatch of gene column $c!\n");
	}
	if (isset($gene2ID[$gene]))
	{
		$col2GID[$c] = $gene2ID[$gene];
	}
	else
	{
		die("Unknown gene '$gene'\n");
	}
}

#
# Now load level 0 to the database!
#

$cids = array();
for($f = 0; $f < $numFacts; $f++)
{
	dbq("insert into clst (lbl,lvl,CRID) values($f,$lvl,$CRID)");
	$CID = dblastid("clst","ID");
	$cids[$lvl][$f] = $CID;	
}

$num_nonzero = 0;
for($f = 0; $f < $numFacts; $f++)
{
	$row = $f+1;
	print "DB insert for factor:$f                            \r";
	$CID = $cids[$lvl][$f];
	$inserts = array();
	for ($c = 1; $c < $nCols; $c++)
	{
		$GID = $col2GID[$c];
		if (isset($wts[$row][$c]) && 
			isset($mis[$row][$c] ) )
		{
			$wt = $wts[$row][$c];
			if ($wt > 0)
			{
				$mi = $mis[$row][$c];
				$inserts[] = "($CRID,$CID,$GID,$wt,$mi)";
			}
		}
		else
		{
			die ("missing weight or MI for ($f,$GID)\n");
		}
	}
	$new_inserts = count($inserts);
	$num_nonzero += $new_inserts;
	if ($new_inserts > 0)
	{
		$insert = implode(",",$inserts);
		dbq("insert into g2c (CRID,CID,GID,wt,mi) values$insert", 0);
	}
	else
	{
		print "Warning: all weights zero for factor $f!!\n";
	}
}
$num_possible = $numFacts*$numGene;
$nonzero_pct = floor(100*((float)$num_nonzero)/((float)$num_possible));
print "level 0: loaded $num_nonzero nonzero ($nonzero_pct%)                                       \n";

#
# higher levels!
#

$lvl++;
$wtfile = $wtfile_pfx.$lvl.$wtfile_sfx;
$mifile = $mifile_pfx.$lvl.$wtfile_sfx;
$numPrevFacts = $numFacts;
while (is_file($wtfile) && is_file($mifile))
{
	print "##############################################\n";
	print "Loading level $lvl weights ($numPrevFacts prior factors)\n";

	$wts = array();
	$mis = array();
	read_matrix($wts,$nRows,$nCols,$wtfile);
	$numFacts = $nRows-1;
	if ($nCols != $numPrevFacts + 1)
	{
		die ("wt file has $nCols columns!\n");
	}
	print "$numFacts new factors\n";
	read_matrix($mis,$nRows,$nCols,$mifile);
	if ($nCols != $numPrevFacts + 1)
	{
		die ("mi file has $nCols cols!\n");
	}
	if ($nRows != $numFacts + 1)
	{
		die ("mi file as $nRows rows!\n");
	}

	for ($f = 0; $f < $numFacts; $f++)
	{
		dbq("insert into clst (lbl,lvl,CRID) values($f,$lvl,$CRID)");
		$CID = dblastid("clst","ID");
		$cids[$lvl][$f] = $CID;	
	}

	$num_nonzero = 0;
	for ($f = 0; $f < $numFacts; $f++)
	{
		$row = $f+1;
		print "DB insert for factor:$f                            \r";
		$CID_new = $cids[$lvl][$f];
		$inserts = array();
		for ($f0 = 0; $f0 < $numPrevFacts; $f0++)
		{
			$col = $f0+1;
			$CID_old = $cids[$lvl-1][$f0];
			if (isset($wts[$row][$col]) && 
				isset($mis[$row][$col] ) )
			{
				$wt = $wts[$row][$col];
				if ($wt > 0)
				{
					$mi = $mis[$row][$col];
					$inserts[] = "($CRID,$CID_old,$CID_new,$wt,$mi)";
				}
			}
			else
			{
				die ("missing weight or MI for ($f,$CID_old)\n");
			}
		}
		$num_inserts = count($inserts);
		$num_nonzero += $num_inserts;
		if ($num_inserts > 0)
		{
			$insert = implode(",",$inserts);
			dbq("insert into c2c (CRID,CID1,CID2,wt,mi) values$insert");
		}
		else
		{
			print "Warning: factor $f has all zero weights!\n";
		}
	}
	$num_possible = $numFacts * $numPrevFacts;
	$nonzero_pct = floor(100*((float)$num_nonzero)/((float)$num_possible));
	print "level $lvl: loaded $num_nonzero nonzero ($nonzero_pct%)                                       \n";

	$lvl++;
	$wtfile = $wtfile_pfx.$lvl.$wtfile_sfx;
	$mifile = $mifile_pfx.$lvl.$wtfile_sfx;
	$numPrevFacts = $numFacts;
	
}
?>


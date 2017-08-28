<?php
require_once("db.php");

#
# From StringDB we have a file gos.human which
# maps "hugo" names to ENSP to GOs.
# 
# These are the GO mappings that we need to use for genes
# in order to match the enrichment that we do from StringDB.
# Also, we need the mappings to ENSP to do the PPI, using
# StringDB graph. 
#
# Unfortunately, the StringDB gene names are not exactly Hugo.
# For one thing, they are out of date. If we are unable to 
# map a string name to an existing name, we will add it
# as a new "hugo" name. 
#

# Goals:
# 1. Add to hugo_lbl table those names which we could not map
# 2. Fill out the goterm table with all the GOs used by StringDB
# 3. Fill out the hugo2go table as completely as possible
# 4. Likewise for hugo2ensp (Note that the target table eprot
#		has already been loaded from the StringDB alias file...
#		these steps might be collapsed in the future).

$stringdb_file = "/local/wnelson/disk/stringdb_files/gos.human.tsv";

# First get the ENSP that we know (from the stringDB alias file)
# so we can check they're not sneaking in any new ones.

$known_eterm = array();
$res = dbq("select term from eprot");
while ($r = $res->fetch_assoc())
{
	$known_eterm[$r["term"]] = 1;
}

# Let's just load the whole thing first

print "Reading $stringdb_file\n";
$hugo2ensp = array();
$ensp2go = array();
$go2desc = array();
$unkensp = array();

$fh = fopen($stringdb_file,"r");
while ( ($line = fgets($fh)) != false)
{
	$fields = explode("\t",trim($line));
	$ensp 	= $fields[1];
	$hugo 	= $fields[2];
	$go 	= $fields[3];
	$desc = $fields[4];
	$conf = $fields[7];

	if (preg_match('/^ENSP/',$hugo))
	{
		continue; # they used the ENSP name as gene name a number of times
	}
	$goterm = preg_replace('/^GO:0*/','',$go);
	$eterm = preg_replace('/^ENSP0*/','',$ensp);
	$hugo = preg_replace('/\.\d+$/','',$hugo); # strip version #

	if (!isset($known_eterm[$eterm]))
	{
		$unkensp[$ensp] = 1;
	}

	$godesc[$goterm] = $desc;

	if (!isset($hugo2ensp[$hugo]))
	{
		$hugo2ensp[$hugo] = array();
	}	
	$hugo2ensp[$hugo][$eterm] = 1;

	if (!isset($ensp2go[$eterm]))
	{
		$ensp2go[$eterm] = array();
	}	
	$ensp2go[$eterm][$goterm] = $conf;
}
fclose($fh);

if (count($unkensp) > 0)
{
	$num = count($unkensp);
	print "Adding $num new ENSP with no descriptions; then re-rerun\n";
	#die("$num unknown ENSPs:\n". implode("\n",array_keys($unkensp)));
	$st = $DB->prepare("insert ignore into eprot (term,descr) values(?,'unknown')");
	$st->bind_param("i",$term);	
	foreach ($unkensp as $ensp => $val)
	{
		$term = preg_replace('/^ENSP0*/','',$ensp);
		$st->execute();
	}
	exit(0);
}

# Load all the go terms first

$numLoaded = 0;
$goterm = 0;
$desc = "";
$st = $DB->prepare("insert ignore into global_gos (term,descr) values(?,?)");
$st->bind_param("is",$goterm,$desc);	
foreach ($godesc as $goterm => $desc)
{
	$st->execute();
	$numLoaded++;
}
print "Loaded $numLoaded GO terms\n";

print "Adding ENSP -> GO mappings...takes a few minutes\n";
# Now load the ensp mapping which is also simple

$numLoaded = 0;
$numEterm = 0;
$goterm = 0;
$eterm = 0;
$conf = 0;
$st = $DB->prepare("insert ignore into esp2go (eterm,gterm,conf) values(?,?,?)");
$st->bind_param("iii",$eterm,$goterm,$conf);	
foreach ($ensp2go as $eterm => $arr)
{
	$numEterm++;
	foreach ($arr as $goterm => $conf)
	{
		$st->execute();
		$numLoaded++;
	}
}
print "Loaded $numLoaded GO terms for $numEterm ENSP terms\n";

#
# Now, map the "hugo" genes to ENSP, adding additional "hugos" for the ones 
# we don't have. 
#
# First we have to get the current names and mapping table
#
$hugoID = array();
$res = dbq("select ID, lbl from hugo_lbl");
while ($r = $res->fetch_assoc())
{
	$lbl = strtolower($r["lbl"]);
	$ID = $r["ID"];
	$hugoID[$lbl] = $ID;
}
$res = dbq("select HID, lbl from map2hugo");
while ($r = $res->fetch_assoc())
{
	$lbl = strtolower($r["lbl"]);
	$ID = $r["HID"];
	$hugoID[$lbl] = $ID;
}

#
# Now figure out which ones we can't map
#
$newnames = array();
foreach ($hugo2ensp as $hugo => $arr)
{
	$hugo_lwr = strtolower($hugo);
	if (!isset($hugoID[$hugo_lwr]))
	{
		$newnames[$hugo] = 1;
	}
}
$totalnames = count($hugo2ensp);
$totalnew = count($newnames);
print "$totalnew new genes out of $totalnames\n";

#
# Chances are we'll never have any use for these names but let's add them anyway
#

$res = dbq("select ID from hugo_type where lbl='unknown'");
$r = $res->fetch_assoc();
$unk_typeID = $r["ID"];

foreach ($newnames as $name => $val)
{
	print "$name\n";
	dbq("insert ignore into hugo_lbl (htype,lbl,src,descr) ".
			"values($unk_typeID,'$name','sdb','') ");
}
# Get the new ID's
$res = dbq("select ID, lbl from hugo_lbl");
while ($r = $res->fetch_assoc())
{
	$lbl = strtolower($r["lbl"]);
	$ID = $r["ID"];
	$hugoID[$lbl] = $ID;
}

# Lastly, add the hugo-to-ensp mapping

$numLoaded = 0;
foreach ($hugo2ensp as $hugo => $arr)
{
	$hugo_lwr = strtolower($hugo);
	if (!isset($hugoID[$hugo_lwr]))
	{
		die ("STILL unknown id $hugo\n");
	}
	$HID = $hugoID[$hugo_lwr];
	foreach ($arr as $eterm => $val)
	{
		$numLoaded++;
		dbq("insert ignore into hugo2esp (HID,term) values($HID,$eterm)");
	}
}
print "Loaded $numLoaded hugo-to-ensp mappings\n";


?>

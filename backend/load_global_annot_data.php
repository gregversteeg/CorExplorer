<?php
require_once("db.php");

# We build a table of gene and protein names which we regard as
# all being essentially equivalent. 
#
# HUGO names, gene types, and some aliases come from genenames.org file.
#
# HUGO names are mapped to ENSP and GO using StringDB files. 
# This ensures that they match results of StringDB enrichment. 
#
$genenames_file = "/local/wnelson/disk/genenames/hgnc_complete_set.txt";

$stringdb_go_file = "/local/wnelson/disk/stringdb_files/gos.human.tsv";
$stringdb_alias_file = "/local/wnelson/disk/stringdb_files/9606.protein.aliases.v10.5.txt";
#
# Note, from the alias file we will only pull the ENSP descriptive names
# We will get the HUGO to ENSP mapping from the gos.human file where it is simpler
#

# May as well load the ENSP defs, they are kind of independent. 
#dbq("delete from eprot");
#load_ensp($stringdb_alias_file);

#dbq("delete from hugo_types");  
#dbq("delete from hugo_obj");
#load_hugo_genes($genenames_file);

dbq("delete from global_go");
dbq("delete from hugo2go");
dbq("delete from h2e");
load_gos_and_ensp_map($stringdb_go_file);

#########################################################

function load_gos_and_ensp_map($go_file)
{
	global $DB;

	# First we need the hugo IDs
	$hugoID = array();
	$res = dbq("select ID,lbl from hugo_obj");
	while ($r = $res->fetch_assoc())
	{
		$lbl_lower = strtolower($r["lbl"]);
		$hugoID[$lbl_lower] = $r["ID"];
	}
	
	#
	# Now read the file. Each line has
	# 9606 <ENSP> <HUGO> <GO> <GO DESC> <GOSRC> <GOEVID> <QUAL>
	#
	# Also there are some previously unknown "hugos" we will be adding
	#
	$fh = fopen($go_file, "r");
	$go2desc = array();
	$h2go = array();
	$h2e = array();
	$hID = 0;
	$goterm = 0;
	$goqual = 0;
	$eterm = 0;
	$st = $DB->prepare("insert into hugo2go (ID,term,qual) values(?,?,?)");
	$st->bind_param("iii",$hID,$goterm,$goqual);	
	$st2 = $DB->prepare("insert into h2e (HID,term) values(?,?)");
	$st2->bind_param("ii",$hID,$eterm);	
	while ( ($line = fgets($fh)) != false)
	{
		$fields = explode("\t",$line);
		$ensp = $fields[1];   
		$hugo = $fields[2];  
		$go = $fields[3];
		$godesc = $fields[4];
		$goqual = $fields[7];

		$hugo = preg_replace('/\.\d+$/','',$hugo);
		$hugo_lower = strtolower($hugo);
		$eterm = preg_replace('/^ENSP0*/','',$ensp);

		if (preg_match('/^ENSP/',$hugo))
		{
			continue;
		}
		if (!isset($hugoID[$hugo_lower]))
		{
			$newID = add_hugo_using_ensp($hugo,$eterm);
			$hugoID[$hugo_lower] = $newID;
			print "new hugo $hugo ID=$newID ENSP=$eterm\n";
			continue;
		}
		$hID = $hugoID[$hugo_lower];
	
		$goterm = preg_replace('/^GO:0*/','',$go);
		if (!is_numeric($goterm))
		{
			die ("bad go term $goterm, line\n$line");
		}
		if (!isset($go2desc[$goterm]))
		{
			$go2desc[$goterm] = $godesc;
		}
		if (!isset($h2go[$hID]))
		{
			$h2go[$hID] = array();
			$h2e[$hID] = array();
		}
		$h2go[$hID][$goterm] = 1;
		$h2e[$hID][$eterm] = 1;
	}
	fclose($fh);
}
##################################################################

function add_hugo_using_ensp($hugo,$eterm)
{
	dbq("insert into hugo_obj (lbl,alias,descr) values('$hugo',0,'description unknown')");
	$hID = dblastid("hugo_obj","ID");
	dbq("update hugo_obj,hugo_types set hugo_obj.otype=hugo_types.ID ".
			" where hugo_obj.ID=$hID and hugo_types.lbl='gene with protein product'");
	dbq("update hugo_obj,eprot set hugo_obj.descr=eprot.descr ".
			" where hugo_obj.ID=$hID and eprot.term=$eterm ");
	return $hID;
	
}

##################################################################


function load_hugo_genes($genenames_file)
{
	global $DB;
	#
	# Load the hugo names, types and descripts
	# We're going to go ahead and load all the "aliases" and "priors" 
	# too, and assign them all the same GOs and ENSP. 
	#

	print "### LOADING $genenames_file ##########################\n";
	$genenames_data = array();
	$fh = fopen($genenames_file,"r");
	$line = fgets($fh);
	$types = array();
	$h2desc = array();
	$h2type = array();
	$h2alias = array();
	$hugo_names = array();
	$alias2hugo = array();
	$allnames = array();
	while ( ($line = fgets($fh)) != false)
	{
		$fields = explode("\t",$line);
		$sym = $fields[1];    	# the hugo name
		$desc = $fields[2];   	# a longer name, we're calling it description
		$type = $fields[4];		# pseudogene, etc.
		$alias = trim($fields[8]);
		$prior = $fields[10];

		if ($type == "withdrawn")
		{
			continue;
		}

		if (isset($hugo_names[$sym]))
		{
			die ("duplicate hugo $sym");
		}
		$hugo_names[$sym] = 1;
		$allnames[$sym] = 1;   # there are some hugos which duplicate names
								# used in prior or aliases, and we will
								# deal with those later by not loading the alias/prior version

		if (!isset($types[$type]))
		{
			$types[$type] = 0;
		}
		$types[$type]++;

		$h2desc[$sym] = $desc;
		$h2type[$sym] = $type;
		$h2alias[$sym] = array();
		if (!empty($prior))
		{
			$prior = preg_replace('/^\"/',"",$prior);
			$prior = preg_replace('/\"$/',"",$prior);
			$plist = explode("|",$prior);
			foreach ($plist as $prior)
			{
				$prior = preg_replace('/\.\d+/',"",$prior);  # get rid of version numbers
				if (isset($allnames[$prior]))
				{
					#print("duplicate prior $prior\n");
					# there aren't too many cases of these duplicated priors
					# names and we will just assign them using their first occurrence
					continue;
				}
				$allnames[$prior] = 1;
				$h2alias[$sym][] = $prior;
				$alias2hugo[$prior] = $sym;
			}
		}
		if (!empty($alias))
		{
			$alias = preg_replace('/^\"/',"",$alias);
			$alias = preg_replace('/\"$/',"",$alias);
			$alias = preg_replace('/\.\d+/',"",$alias);
			$alist = explode("|",$alias);
			foreach ($alist as $alias)
			{
				$alias = preg_replace('/\.\d+/',"",$alias);  # get rid of version numbers
				if (isset($allnames[$alias]))
				{
					#print ("duplicate alias $alias\n");
					# there aren't too many cases of these duplicated priors
					# names and we will just assign them using their first occurrence
					continue;
				}
				$allnames[$alias] = 1;
				$h2alias[$sym][] = $alias;
				$alias2hugo[$alias] = $sym;
			}
		}
	}
	fclose($fh);
	$num_all = count($allnames);
	$num_hugo = count($hugo_names);
	print "Loaded $num_hugo hugos and $num_all total\n";


	#
	# Fill out the hugo types table and get their IDs
	#
	foreach ($types as $type => $count)
	{
		dbq("insert into hugo_types (lbl) values('$type')");
	}

	$htype2ID = array();
	$res = dbq("select ID, lbl from hugo_types");
	while ($r = $res->fetch_assoc())
	{
		$htype2ID[$r["lbl"]] = $r["ID"];
	}
	print_r($htype2ID);

	#
	# Now fill out the hugo gene table. 
	# First load the main entries and get their IDs.
	#

	$hugoID = array();
	$typeID = 0;
	$desc = "";
	$sym = "";
	$alias = 0;
	$st = $DB->prepare("insert into hugo_obj (otype,lbl,descr,alias) values(?,?,?,?)");
	$st->bind_param("issi",$typeID,$sym,$desc,$alias);	
	$numLoaded = 0;
	foreach ($hugo_names as $sym => $val)
	{
		print "Loading $sym                      \r";
		$desc = $h2desc[$sym];
		$type = $h2type[$sym];
		$typeID = $htype2ID[$type];
		$st->execute();
		$hugoID[$sym] = dblastid("hugo_obj","ID");
		$numLoaded++;
	}
	print "Loaded $numLoaded primary hugo genes\n";

	#
	# Now load the alias entries.
	#
	$numLoaded = 0;
	foreach ($hugo_names as $sym1 => $val)   # we need $sym for the prepared statement
	{
		$alias = $hugoID[$sym1];
		foreach ($h2alias[$sym1] as $sym)
		{	
			print "Loading $sym                      \r";
			$desc = $h2desc[$sym1];
			$type = $h2type[$sym1];
			$typeID = $htype2ID[$type];
			$st->execute();
			$numLoaded++;
		}
	}
	print "Loaded $numLoaded alias hugo names\n";

}


###############################################################

function load_ensp($alias_file)
{
	global $DB;
	$fh = fopen("$alias_file","r");
	$term_seen = array();
	$st = $DB->prepare("insert into eprot (term,descr) values(?,?)");
	$st->bind_param("is",$term,$descr);	
	dbq("start transaction");
	$added = 0;
	$total = 0;
	$lines = 0;
	while (($line = fgets($fh)) != false) 
	{
		if (!preg_match('/Ensembl_HGNC_Approved_Name/',$line))
		{
			continue;
		}
		$lines++;
		$fields = preg_split('/\t/',$line);
		if (count($fields) < 3) 
		{
			continue;
		}
		if (preg_match('/Ensembl_HGNC_Approved_Name/',$fields[2]))
		{
			$term = preg_replace('/9606.ENSP0*/','',$fields[0]);	
			if (isset($term_seen[$term]))
			{
				print( "repeated:$term\n");
				continue;
			}
			$term_seen[$term] = 1;
			$descr = $fields[1];
			$st->execute();
			$added++;
			if ($added >= 100)
			{
				dbq("commit");
				dbq("start transaction");
				$total += $added;
				$added=0;
				print "$total         \r";
			}
		}	
		else
		{
			die($line);
		}
	}
	if ($added > 0)
	{
		dbq("commit");
		$total += $added;
	}
	print "ENSP loaded:$total  $lines lines       \n";
	fclose($fh);
}
?>

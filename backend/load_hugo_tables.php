<?php
require_once("db.php");

#  
# The hgnc_complete file from genenames.org has all the HUGO names
# along with some aliases.
#
$hgnc_complete_file = "/local/wnelson/disk/genenames/hgnc_complete_set.txt";

#
# The gencode files have maps from ENSG to HUGO
# Note ENSG numbers change with release
#
$gencode22 = "/local/wnelson/disk/gencode/gencode.v22.annotation.gff3";
$gencode26 = "/local/wnelson/disk/gencode/gencode.v26.annotation.gff3";

#
# First load the HUGO names and descriptions, along with aliases and priors,
# which we will map to the primary name. 
#
load_hgnc_names($hgnc_complete_file);

#
# Now load the ENSG map to hugo names
# This includes many names that are NOT hugo, which
# we will add to the "hugo" table, with src="gcode"
#
$ensg2name = array();
$newNames = array();
read_genecode_ensg_map($gencode26, $ensg2name, $newNames);
read_genecode_ensg_map($gencode22, $ensg2name, $newNames);

print("Loading new names to DB\n");

$res = dbq("select ID from hugo_type where lbl='unknown'");
$r = $res->fetch_assoc();
$unk_typeID = $r["ID"];

foreach ($newNames as $name => $val)
{
	dbq("insert ignore into hugo_lbl (lbl,htype,descr,src) ".
			" values('$name',$unk_typeID,'','gcode')");
}
# 
# Get the current (final) list of "hugo" ID's and aliases, to which
# all ENSG should now map.
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
	$hugoID[strtolower($r["lbl"])] = $r["HID"];
}

# Check that they all match, then load
foreach ($ensg2name as $ensg => $hugo)
{
	if (!isset($hugoID[strtolower($hugo)]))
	{
		die("$ensg does not match to any existing name!\n");
	}
}
print "Loading ENSG mappings to DB\n";
# Because we skipped the (two) ENSG names that were already in the HGNC file,
# we are not expected any of these ENSG to be already in the mapping table. 
# Hence, no insert ignore. 
foreach ($ensg2name as $ensg => $hugo)
{
	$HID = $hugoID[strtolower($hugo)];
	dbq("insert into map2hugo (lbl,HID,src) values('$ensg',$HID,'gcode')");
}


function read_genecode_ensg_map($gfile, &$ensg2name,  &$newNames)
{
	global $DB;

	# First we need the hugo gene IDs AND for aliases
	# Save them as lowercase so we don't have case problems
	$hugoID = array();
	$real_hugoID = array();
	$res = dbq("select ID, lbl, src from hugo_lbl");
	while ($r = $res->fetch_assoc())
	{
		$src = $r["src"];
		$lbl = strtolower($r["lbl"]);
		$ID = $r["ID"];
		$hugoID[$lbl] = $ID;
		if ($src == "hgnc")
		{
			$real_hugoID[$lbl] = $ID;
		}
	}
	$res = dbq("select HID, lbl from map2hugo");
	while ($r = $res->fetch_assoc())
	{
		$lbl = strtolower($r["lbl"]);
		$HID = $r["HID"];
		$hugoID[$lbl] = $HID;
	}

	print "### LOADING $gfile ##########################\n";
	$fh = fopen($gfile,"r");
	while ( ($line = fgets($fh)) != false)
	{
		$fields = explode("\t",$line);
		if (count($fields) < 9)
		{
			continue;
		}
		$type = $fields[2];
		if ($type != "gene")
		{
			continue;
		}
		$mess = $fields[8];
		$matches = array();
		if (preg_match('/.*ID=(ENSG\d+)/',$mess,$matches))
		{
			$ensg = $matches[1];
			if (isset($hugoID[strtolower($ensg)]))
			{
				# The HGNC file has two ENSG names in it already mapped to hugo names.  
				continue; 
			}
			if (preg_match('/.*gene_name=([^;]+)/',$mess,$matches))
			{
				$hugo = preg_replace('/\.\d+$/',"",$matches[1]); # get rid of version numbers
				#print "$hugo\t$ensg\n";
				if (isset($ensg2name[$ensg]))
				{
					# if we hit one that was already mapped, take it 
					#  only if it is a legitimate hugo name
					$oldname = $ensg2name[$ensg];
					if ($hugo != $oldname)
					{
						#print "$ensg maps to $hugo but previously to $oldname\n";
					}
					if (isset($real_hugoID[strtolower($hugo)]))
					{
						$ensg2name[$ensg] = $hugo;
					}
				}
				$ensg2name[$ensg] = $hugo;
				if (!isset($hugoID[strtolower($hugo)]))
				{
					$newNames[$hugo] = 1;
				}
			}
			else
			{
				die ("No gene name for $ensg\n!");  # they all have names
			}
		}
	}
	fclose($fh);
	$numTotal = count($ensg2name);
	$numNew = count($newNames);
	print "$numTotal ENSG, $numNew unknown names\n";
}
function load_hgnc_names($hgnc_complete_file)
{
	global $DB;

	print "### LOADING $hgnc_complete_file ##########################\n";
	$genenames_data = array();
	$fh = fopen($hgnc_complete_file,"r");
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
	print "Read $num_hugo hugos and $num_all total\n";


	#
	# Fill out the hugo types table and get their IDs
	#
	foreach ($types as $type => $count)
	{
		dbq("insert ignore into hugo_type (lbl) values('$type')");
	}

	$htype2ID = array();
	$res = dbq("select ID, lbl from hugo_type");
	while ($r = $res->fetch_assoc())
	{
		$htype2ID[$r["lbl"]] = $r["ID"];
	}
	print_r($htype2ID);

	# Now fill out the hugo gene table. 
	# First load the main entries and get their IDs.
	#

	$typeID = 0;
	$desc = "";
	$sym = "";
	$st = $DB->prepare("insert ignore into hugo_lbl (htype,lbl,descr) values(?,?,?)");
	$st->bind_param("iss",$typeID,$sym,$desc);	
	$numLoaded = 0;
	foreach ($hugo_names as $sym => $val)
	{
		print "Loading $sym                      \r";
		$desc = $h2desc[$sym];
		$type = $h2type[$sym];
		$typeID = $htype2ID[$type];
		$st->execute();
		$numLoaded++;
	}
	print "Loaded $numLoaded primary hugo genes\n";

	# Get the hugo ID's from the table. Note, we can't use 
	# lastid from the previous because it won't work if we were re-running
	# the script with the table already filled out. 

	$hugoID = array();
	$res = dbq("select ID, lbl from hugo_lbl");
	while ($r = $res->fetch_assoc())
	{
		# Here we don't have to worry about case since we know they agree
		$hugoID[$r["lbl"]] = $r["ID"];
	}

	#
	# Now load the alias entries.
	#
	$numLoaded = 0;
	$HID = 0;
	$alias = 0;
	$st = $DB->prepare("insert ignore into map2hugo (lbl,HID,src) values(?,?,'hgnc')");
	$st->bind_param("si",$alias,$HID);	
	foreach ($hugo_names as $sym => $val)
	{
		$HID = $hugoID[$sym];
		foreach ($h2alias[$sym] as $alias)
		{	
			print "Loading $alias                      \r";
			$st->execute();
			$numLoaded++;
		}
	}
	print "Loaded $numLoaded alias hugo names\n";
}


?>

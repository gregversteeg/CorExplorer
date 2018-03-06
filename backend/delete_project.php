<?php
require_once("util.php");

$logfile = "$DATADIR/delete.log";
$LOG = fopen("$DATADIR/delete.log","w");

$projname = trim($argv[1]);
$fromweb = 0;
if (isset($argv[2]))
{
	$fromweb = ($argv[2] == "web" ? 1 : 0);
}

if (!preg_match("/\S/",$projname))
{
	# prevent from deleting the whole datadir!!
	logstr("empty project name!!"); 
	die();
}

$st = dbps("select id,dsid,glid,projdir from clr where lbl=?");
$st->bind_param("s",$projname);
$st->bind_result($CRID,$DSID,$GLID,$projdir_path);
$st->execute();
if (!$st->fetch())
{
	logstr("$projname not found in DB");
	die();
}
$st->close();

#
# Now this is kind of kludjy. 
# We store the location of the uploaded data in the DB, so that if
# project name gets changed, we still know where the data is. 
# However we want to allow for the overall data directory to be moved. 
# So we're just going to strip off the last directory name from the
# original path, and append that to the current data location.
#
$proj_data_dirname = basename($projdir_path);
if (!preg_match("/\S/",$proj_data_dirname))
{
	# prevent from deleting the whole datadir!!
	logstr("empty project name!!"); 
	die();
}

$projdir = "$DATADIR/$proj_data_dirname";
if (!is_dir($projdir))
{
	logstr("Can't find data directory $projdir");
	die();
}

if (!$fromweb)
{
	yesno("Delete project $projname (CRID=$CRID)");
}

logstr("delete $proj_data_dirname directory");
system("rm -Rf $projdir");

logstr("Deleting $projname tables");

if ($CRID != 0)
{
	logstr("Deleting expression data");
	clear_expr($CRID);
}
if ($CRID != 0)
{
	dbqlog("delete from clr where ID=$CRID");
	dbqlog("delete from survdt_ov where CRID=$CRID");
	dbqlog("delete clst_pair.* from clst_pair, clst where clst_pair.CID1=clst.ID and clst.crid=$CRID");
	dbqlog("delete pair_survdt.* from pair_survdt, clst where pair_survdt.CID1=clst.ID and clst.crid=$CRID");
	dbqlog("delete pair_lbls.* from pair_lbls, clst where pair_lbls.CID1=clst.ID and clst.crid=$CRID");
	dbqlog("delete clst2go.* from clst2go, clst where clst.crid=$CRID and clst2go.cid=clst.id");
	dbqlog("delete clst2kegg.* from clst2kegg, clst where clst.crid=$CRID and clst2kegg.cid=clst.id");
	dbqlog("delete lbls.* from lbls, clst where clst.crid=$CRID and lbls.cid=clst.id");
	dbqlog("delete survdt.* from survdt, clst where clst.crid=$CRID and survdt.cid=clst.id");
	$tables = array("clst","g2c","c2c","gos","kegg");
	delete_from_tables($tables,"CRID",$CRID);
}
if ($DSID != 0)
{
	dbqlog("delete sampdt.* from sampdt, samp where sampdt.sid=samp.id and samp.dsid=$DSID");
	dbqlog("delete sampalias.* from sampalias, samp where sampalias.sid=samp.id and samp.dsid=$DSID");
	dbqlog("delete from dset where ID=$DSID");
	$tables = array("samp","expr","clr");
	delete_from_tables($tables,"DSID",$DSID);
}
if ($GLID != 0)
{
	dbqlog("delete g2e.* from g2e, glist where glist.glid=$GLID and g2e.gid=glist.id");
	dbqlog("delete from glists where ID=$GLID");
	$tables = array("glist");
	delete_from_tables($tables,"GLID",$GLID);
}
logstr("Delete done");
fclose($LOG);
system("chmod 777 $logfile"); # so there won't be a problem with writing
							  # by the web vs. writing by command line execution

#############################################

function delete_from_tables(&$tables,$key,$val)
{
	foreach ($tables as $table)
	{
		dbqlog("delete from $table where $key = $val");
	}
}

#############################################
#
# incremental delete of expression - easier on mysql
# than one big delete
#

function clear_expr($CRID)
{
	global $LOG, $fromweb;
	$info = array();
	load_proj_data($info,$CRID);
	$DSID = $info["DSID"];
	$GLID = $info["GLID"];
		
	logstr("Clear expression table for CRID=$CRID");
	$gids = array();
	$res = dbq("select ID from glist where GLID=$GLID");
	while ($r = $res->fetch_assoc())
	{
		$gids[] = $r["ID"];
	}
	$nGenes = count($gids);
	foreach ($gids as $GID)
	{
		if ($GID % 1000 == 0)
		{
			logstr("delete from expr where DSID=$DSID and GID=$GID");
		}
		dbq("delete from expr where DSID=$DSID and GID=$GID");
		$nGenes--;
	}
}

########################################

function logstr($str)
{
	global $LOG, $fromweb;
	fwrite($LOG,"$str\n");
	if (!$fromweb)
	{
		print "$str\n";
	}
}
########################################

function dbqlog($sql)
{
	global $LOG;
	logstr("$sql");
	dbq($sql);
}
?>

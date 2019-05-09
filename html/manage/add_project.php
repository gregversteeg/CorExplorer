<?php
require_once("../util.php");
login_init();
require_login();
if (!can_load_data())
{
	die("No permission to load data!");
}

#
# 1. Check for valid project name etc
# 2. Create project dir and initialize project tables (mainly in order to be able to report status immediately)
# 3. Call the load script which will download the zip and do the rest

$DATADIR = $_SERVER["COREXDATADIR"];
$SCRIPTDIR = $_SERVER["COREXSCRIPTDIR"];

$Projname = $_GET["projname"];
$Datalink = $_GET["datalink"];

if (!preg_match("/^[\w\.]+$/",$Projname))
{
	print "Invalid project name: can contain letters, numbers, underscore, periods<br>";
	exit(0);
}

$st = dbps("select id from clr where lbl=?");
$st->bind_param("s",$Projname);
$st->execute();
if ($st->fetch())
{
	die("Project $Projname exists\n");
}

$dataset_dir = "$DATADIR/$Projname";
if (is_dir($dataset_dir) || is_file($dataset_dir))
{
	die("Data directory $Projname already exists\n");
}
mkdir($dataset_dir);
if (!is_dir($dataset_dir))
{
	die("Unable to create directory $Projname\n");
}
system("chmod 777 $dataset_dir");

# Probably should not have defined 3 different IDs. 
# Now we have to make sure and clear them all if there's a fail. 
$DSID = 0;
$GLID = 0;
$CRID = 0;

$s = dbps("insert into dset (lbl,expr_type) values(?,'fpkm')" );
$s->bind_param("s",$Projname);
$succeed_dsid = ($s->execute() ? 1 : 0);
$s->close();
if (!$succeed_dsid)
{
	error_log($s->error);
}
$DSID = dblastid("dset","ID");

$s = dbps("insert into glists (descr) values(?)");
$s->bind_param("s",$Projname);
$succeed_glid = ($s->execute() ? 1 : 0);
$s->close();
$GLID = dblastid("glists","ID");
if (!$succeed_glid)
{
	error_log($s->error);
}

$s = dbps("insert into clr (lbl,meth,GLID,DSID,projstat,dataurl,ownedby,projdir,load_dt) values(?,'corex',?,?,'START',?,?,?,NOW())");
$s->bind_param("siisis",$Projname,$GLID,$DSID,$Datalink,$USERID,$dataset_dir);
$succeed_crid = ($s->execute() ? 1 : 0);
$s->close();
if (!$succeed_crid)
{
	error_log($s->error);
}
$CRID = dblastid("clr","ID");
#error_log("$succeed_dsid,$succeed_glid,$succeed_crid");

if (!$succeed_crid || !$succeed_glid || !$succeed_dsid)
{
	if ($succeed_dsid)
	{
		dbq("delete from dset where id=$DSID");
	}
	if ($succeed_glid)
	{
		dbq("delete from glists where id=$GLID");
	}
	if ($succeed_crid)
	{
		dbq("delete from clr where id=$CRID");
	}
	system("rmdir $dataset_dir");
	print "Project load failed\n";
	die();
}

$s = dbps("insert into access (uid,crid) values(?,?)");
$s->bind_param("ii",$USERID,$CRID);
$s->execute();
$s->close();

$logfile = "$dataset_dir/load.log";
$cmd = "/usr/bin/php $SCRIPTDIR/load_project.php WEB $CRID";
$fullcmd = "DBUSER=$DBUSER DBPASS=$DBPASS COREXDATADIR=$DATADIR COREXSCRIPTDIR=$SCRIPTDIR $cmd  > $logfile 2>&1 &";
#error_log($fullcmd);
exec($fullcmd);

header('Location: /manage/manage_projects.php');
die();

function dbq($sql, $record=0)
{
	global $DB;
	if ($record)
	{
		file_put_contents("mysql.log","$sql\n",FILE_APPEND);
	}
	$res = $DB->query($sql);
	if (!$res)
	{
		print ($DB->error."\n");
	}
	return $res;
}


?>

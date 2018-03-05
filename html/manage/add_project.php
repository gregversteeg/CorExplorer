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

$datadir = $_SERVER["COREXDATADIR"];
$scriptdir = $_SERVER["COREXSCRIPTDIR"];

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

$dataset_dir = "$datadir/$Projname";
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
$succeed_dsid = $s->execute();
$s->close();
#$succeed_dsid = dbq("insert into dset (lbl,expr_type) values('$Projname','fpkm')" );
$DSID = dblastid("dset","ID");

$s = dbps("insert into glists (descr) values(?)");
$s->bind_param("s",$Projname);
$succeed_glid = $s->execute();
$s->close();
#$succeed_glid = dbq("insert into glists (descr) values('$Projname')");
$GLID = dblastid("glists","ID");

$s = dbps("insert into clr (lbl,meth,GLID,DSID,projstat,dataurl,ownedby,projdir) values(?,'corex',?,?,'START',?,?,?)");
$s->bind_param("siisis",$Projname,$GLID,$DSID,$Datalink,$USERID,$dataset_dir);
$succeed_crid = $s->execute();
$s->close();
#$succeed_crid = dbq("insert into clr (lbl,meth,GLID,DSID,projstat,dataurl,ownedby,projdir) ".
#			" values('$Projname','corex','$GLID','$DSID','START','$Datalink',$USERID,'$dataset_dir')");
$CRID = dblastid("clr","ID");

if (!$succeed_crid || !$succeed_glid || !$succeed_dsid)
{
	dbq("delete from dset where id=$DSID");
	dbq("delete from glists where id=$GLID");
	dbq("delete from clr where id=$CRID");
	system("rmdir $dataset_dir");
	print "Project load failed\n";
	die();
}

$s = dbps("insert into access (uid,crid) values(?,?)");
$s->bind_param("ii",$USERID,$CRID);
$s->execute();
$s->close();

$logfile = "$dataset_dir/load.log";
$cmd = "/usr/bin/php $scriptdir/load_project.php WEB $CRID";
$fullcmd = "DBUSER=$DBUSER DBPASS=$DBPASS $cmd  > $logfile 2>&1 &";
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

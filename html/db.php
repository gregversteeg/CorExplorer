<?php

$DBUSER = $_SERVER["DBUSER"];
$DBPASS = $_SERVER["DBPASS"];
$DB = mysqli_connect("localhost",$DBUSER,$DBPASS,"corex");

if (mysqli_connect_errno())
{
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
}
# main purpose of this function is to remove the need to
# have global $DB in other functions
function dbps($sql,$log=0)
{
	global $DB;
	if ($log)
	{
		error_log($sql);
	}
	$st=$DB->prepare($sql);
	if (!$st)
	{
		error_log($st->error);
		die($st->error);
	}	
	return $st;
}
function table_exists($tbl)
{
	global $DB;
	$q = $DB->query("select count(*) from $tbl");
	if ($q == false)
	{
		return false;
	}
	return true;
}

function dba($sql)
{
	global $DB;
	$res = $DB->query($sql);
	if (!$res)
	{
		die ($DB->error);
	}
	return $res->fetch_assoc();
}
function dblastid($table, $id)
{
	$r = dba("select max($id) as lastid from $table");
	return $r["lastid"];
}
?>

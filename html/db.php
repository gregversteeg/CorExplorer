<?php
$DB = mysqli_connect("localhost","root","corex","corex");

if (mysqli_connect_errno())
{
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
}
function reconnect()
{
	$DB = mysqli_connect("localhost","root","corex","corex");

	if (mysqli_connect_errno())
	{
	  	echo "Failed to connect to MySQL: " . mysqli_connect_error();
		return 0;
	}
	return 1;
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
	return $DB->prepare($sql);
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


?>

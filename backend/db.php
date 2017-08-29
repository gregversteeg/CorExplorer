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

function dbps($sql,$vals,$types)
{
	global $DB;

	$ps = $DB->prepare($sql);
	if (!$ps)
	{
		die ("Prepare failed: ".$DB->error);
	}
	$vars = array();
	$vars[] =&$types;
	for ($i = 1; $i <= count($vals); $i++)
	{
		$vars[] = &$vals[$i-1];
	}
	call_user_func_array(array($ps, 'bind_param'), $vars);
	$ps->execute();
}
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
		die ($DB->error);
	}
	return $res;
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
function dblastid($table, $id)
{
	$r = dba("select max($id) as lastid from $table");
	return $r["lastid"];
}
function entry_exists($table,$id,$value)
{
	$r = dba("select count(*) as N from $table where $id='$value'");
	return ($r["N"] == 0 ? 0 : 1);
}
function entry_exists2($table,$id,$value, $id2, $value2)
{
	$r = dba("select count(*) as N from $table where $id='$value' and $id2='$value2'");
	return ($r["N"] == 0 ? 0 : 1);
}
function table_has_column($table, $column)
{
	$res = dbq("SHOW COLUMNS FROM $table LIKE '$column'");
	return ($res->num_rows > 0);
}


?>
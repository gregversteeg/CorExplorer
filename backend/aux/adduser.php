<?php
require_once("util.php");

$usr = "";
$pass = "";
$descr = "";
$admin = 0;

$hash = hash("sha256",$pass);

$s = dbps("insert into usrs (usr,passwd,descr,uadmin) values(?,?,?,?)",1);
$s->bind_param("sssi",$usr,$hash,$descr,$admin);
$s->execute();
if ($s->error != "")
{
	die("Error:".$s->error."\n");
}
$s->close();

$s = dbps("select uid from usrs where usr=?");
$s->bind_param("s",$usr);
$s->bind_result($uid);
$s->execute();
$ret = $s->fetch();
$s->close();
if ($ret == TRUE)
{
	print "Added UID=$uid\n";
}
else
{
	print "User not added!\n";
}


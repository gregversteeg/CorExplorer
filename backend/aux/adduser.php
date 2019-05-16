<?php

$DBUSER = "";
$DBPASS = "";

$corex_newuser = "";
$corex_newpass = "";
$corex_user_isadmin = 0;

require_once("../util.php");

$hash = hash("sha256",$corex_newpass);

$s = dbps("insert into usrs (usr,passwd,descr,uadmin) values(?,?,'',?)",1);
$s->bind_param("ssi",$corex_newuser,$hash,$corex_user_isadmin);
$s->execute();
if ($s->error != "")
{
	die("Error:".$s->error."\n");
}
$s->close();

$s = dbps("select uid from usrs where usr=?");
$s->bind_param("s",$corex_newuser);
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


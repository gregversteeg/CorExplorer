<?php
require_once("../util.php");
login_init();
require_login();
if (!has_admin_access())
{
	die("No access");
}
head_section("Manage Users");

handle_actions();

?>
<body>
<table cellspacing=0 cellpadding=0 width="100%">
	<tr>
		<td colspan=2 align=left>
			<table width='100%'  cellpadding=0 cellspacing=0 
					class="graybord" >
				<tr>
					<td width='100%' height='20' align=left colspan=2>
						<table  cellpadding=0 cellspacing=0 width=100% class="graybord" style="background-color:#f5f5f5;">
							<tr>
								<td align=left style="cursor:pointer" onclick="location.href='/'">
									<img src="/logo.png">
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td colspan=2 align=left  style="padding:20px">
<h3>Manage Users</h3>

<table border=1 rule=all cellpadding=3 >
	<tr style='font-weight:bold'>
		<td>User</td><td>IsAdmin</td><td>CanLoadData</td><td>Disabled</td><td>Date Added</td>
	</tr>
<?php
$st = dbps("select usr,uadmin,addprj,disab,adddate from usrs order by uid asc");
$st->bind_result($uname,$admin,$addprj,$disab,$adddate);
$st->execute();
while ($st->fetch())
{
	print <<<END
	<tr>
		<td>$uname</td><td>$admin</td><td>$addprj</td><td>$disab</td><td>$adddate</td></tr>
	</tr>
END;

}
?>
</table>
<h4>Add User</h4>
<form method='put'>
<input type='hidden' name='action' value='add'>
<table>
	<tr>
		<td>Username:</td>
		<td><input type='text' size='20' name='uname'> (letters, numbers, underscore)</td>
	</tr>
	<tr>
		<td>Password:</td>
		<td><input type='text' size='20' name='pwd'</td>
	</tr>
	<tr>
		<td colspan=2 align='left'>
			<input type='submit' value='Submit'>
		</td>
	</tr>
</table>
		
</form>
		</td>
	</tr>
</table>

</body>


<?php

###############################################################################

function handle_actions()
{
	$action = getval("action","");
	if ($action == "add")
	{
		add_user();
	}
	else if ($action == "changepwd")
	{

	}
	
}
function add_user()
{
	global $ERROR;
	$uname = trim(getval("uname",""));
	$pwd = trim(getval("pwd",""));
	if (preg_match('/^[\w]/',$uname)
	{
		$ERROR = "Invalid username (please use letters, numbers, underscores)";
		return;
	}
	if (preg_match('/\s/',$pwd))
	{
		$ERROR = "Invalid password (no spaces!)";
		return;

	}
	$hash = hash("sha256",$pass);
 
	$s = dbps("insert into usrs (usr,passwd,descr,uadmin) values(?,?,?,?)",1);
	$s->bind_param("sssi",$usr,$hash,$descr,$admin);
	$s->execute();
	if ($s->error != "")
	{
	  die("Error:".$s->error."\n");
	}
	$s->close();

}
?>

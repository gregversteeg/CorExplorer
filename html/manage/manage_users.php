<?php
require_once("../util.php");
login_init();
require_login();
if (!has_admin_access())
{
	die("No access");
}
head_section("Manage Users");

$ERROR = "";
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
		<td>User</td><td>IsAdmin</td><td>Disabled</td><td>Date Added</td>
	</tr>
<?php
$st = dbps("select usr,uadmin,addprj,disab,adddate from usrs order by uid asc");
$st->bind_result($uname,$admin,$addprj,$disab,$adddate);
$st->execute();
while ($st->fetch())
{
	print <<<END
	<tr>
		<td>$uname</td><td>$admin</td><td>$disab</td><td>$adddate</td></tr>
	</tr>
END;

}
?>
</table>
<?php
if ($ERROR != "")
{
	print "<div style='color:red;margin-top:20px'>$ERROR</div>";
}
?>
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

<h4>Disable or Re-enable User</h4>
<form method='put'>
<input type='hidden' name='action' value='disab'>
<table>
	<tr>
		<td>Username:</td>
		<td><?php echo user_sel('uid','uid_sel_dis',1)?></td>
	</tr>
	<tr>
		<td colspan=2 align='left'>
			<input type='submit' value='Submit'>
		</td>
	</tr>
</table>
		
</form>

<h4>Change Password</h4>
<form method='put'>
<input type='hidden' name='action' value='chpw'>
<table>
	<tr>
		<td>Username:</td>
		<td><?php echo user_sel('uid','uid_sel')?></td>
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

<h4>Delete User</h4>
(remove all of their projects first)
<form method='put'>
<input type='hidden' name='action' value='delete'>
<table>
	<tr>
		<td>Username:</td>
		<td><?php echo user_sel('uid','uid_sel_del',1)?></td>
	</tr>
	<tr>
		<td colspan=2 align='left'>
			<input type='submit' value='Submit'>
		</td>
	</tr>
</table>
		</td>
	</tr>
</table>

</body>


<?php

###############################################################################

function handle_actions()
{
	global $ERROR;
	$action = getval("action","");
	if ($action == "")
	{
		return;
	}
	if ($action == "add")
	{
		add_user();
	}
	else if ($action == "chpw")
	{
		change_pw();
	}
	else if ($action == "disab")
	{
		disable_user();
	}
	else if ($action == "delete")
	{
		delete_user();
	}
	if ($ERROR == "")
	{
		strip_query_and_reload();		
	}
}
function add_user()
{
	global $ERROR;
	$uname = trim(getval("uname",""));
	$pwd = trim(getval("pwd",""));
	if (preg_match('/[^\w]/',$uname))
	{
		$ERROR = "Invalid username (please use letters, numbers, underscores)";
		return;
	}
	if (preg_match('/\s/',$pwd))
	{
		$ERROR = "Invalid password (no spaces!)";
		return;
	}
	$hash = hash("sha256",$pwd);

	$descr = "";
	$admin = 0;
	$s = dbps("insert into usrs (usr,passwd,descr,uadmin) values(?,?,?,?)",1);
	$s->bind_param("sssi",$uname,$hash,$descr,$admin);
	$s->execute();
	if ($s->error != "")
	{
	  die("Error:".$s->error."\n");
	}
	$s->close();

}
function change_pw()
{
	global $ERROR, $USERID;
	$uid = getint("uid",0,1);
	$pwd = trim(getval("pwd","",1));

	if (preg_match('/\s/',$pwd))
	{
		$ERROR = "Invalid password (no spaces!)";
		return;
	}
	$hash = hash("sha256",$pwd);

	$st = dbps("update usrs set passwd=? where uid=?");
	$st->bind_param("si",$hash,$uid);
	$st->execute();
	$st->close();
}
function disable_user()
{
	global $ERROR;
	$uid = getint("uid",0,1);

	$st = dbps("update usrs set disab=NOT disab where uid=?");
	$st->bind_param("i",$uid);
	$st->execute();
	$st->close();
}
function user_sel($name,$id,$showall=0)
{
	$where = ($showall ? "" : " where disab=0 ");
	$st = dbps("select usr,UID from usrs $where");
	$st->bind_result($uname,$uid);
	$st->execute();
	$html = "<select name='$name' id='$id'>\n";
	while ($st->fetch())
	{
		$html .= "<option value='$uid'>$uname</option>\n";
	}
	$html .= "</select>\n";
	return $html;
}
function delete_user()
{
	global $ERROR;
	$uid = getint("uid",0,1);

	$st = dbps("select count(*) from clr where ownedby=?");
	$st->bind_param("i",$uid);
	$st->bind_result($num_proj);
	$st->execute();
	$st->fetch();
	$st->close();

	if ($num_proj > 0)	
	{
		$ERROR = "Remove projects before deleting user";
		return;
	}
	$st = dbps("delete from usrs where uid=?");
	$st->bind_param("i",$uid);
	$st->execute();
	$st->close();
}
?>

<?php
require_once("db.php");
session_start();

$LOGGED_IN = 0;
$USERNAME = "";
$USERID = 0;
$ADMIN = 0;
$ACCESS = array();

$LOGIN_MSG = login_init();

$head_xtra = "";

$MaxClstLvl = 2;

# Check crid access here and in the future always use the lowercase parameter
if (isset($_GET["CRID"]))
{
	check_read_access($_GET["CRID"]);
}
else if (isset($_GET["crid"]))
{
	check_read_access($_GET["crid"]);
}

function ensp_name($num)
{
	# return name of form ENSP00000323929
	while (strlen($num) < 11)
	{
		$num = "0$num";
	}	
	return "ENSP$num";
}
function go_name($num)
{
	# return name of form GO:0000016
	while (strlen($num) < 7)
	{
		$num = "0$num";
	}	
	return "GO:$num";
}
function kegg_name($num)
{
	# return name of form 00161
	while (strlen($num) < 5)
	{
		$num = "0$num";
	}	
	return "$num";
}

#######################################################
#
# Below here functions related to form values
#
#######################################################

function getval($lbl,$default,$required=0)
{
	if (isset($_GET[$lbl]))
	{
		return trim($_GET[$lbl]);
	}
	else if (isset($_POST[$lbl]))
	{
		return trim($_POST[$lbl]);
	}
	else if ($required == 1)
	{
		die ("Missing required parameter $lbl\n");
	}
	return trim($default);
}
function getint($lbl,$default,$required=0)
{
	$val = getval($lbl,$default,$required);
	if (!preg_match('/^\d+$/',$val))
	{
		die("invalid $lbl");	
	}
	return $val;
}
function getnum($lbl,$default,$required=0)
{
	$val = getval($lbl,$default,$required);
	if (!is_numeric($val))
	{
		die("invalid $lbl");	
	}
	return $val;
}
# page parameter which is letters, numbers, or underscores
function getw($lbl,$default,$required=0)
{
	$val = getval($lbl,$default,$required);
	if (!preg_match('/^\w*$/',$val))
	{
		die("invalid $lbl");	
	}
	return $val;
}
function checked($val)
{
	if ($val)
	{
		print " checked='true' ";
	}
}
# NEEDED IF TRYING TO MAKE A CHECKBOX DEFAULT TO CHECKED
# fromForm tells us if the form was actually submitted or the page was called
# with empty query. 
# The problem is that when a checkbox is not checked, it
# often puts nothing in the query string, so you can't tell the difference
# between submitted with uncheck, and initial page load with empty query. 
#
function checkbox_val($lbl,$default,$fromForm=1)
{
	if (isset($_GET[$lbl]))
	{
		return ($_GET[$lbl] == "1" || $_GET[$lbl] == "checked" || $_GET[$lbl] == "on" ? 1 : 0);
	}
	if ($fromForm == 1)
	{
		# we cam from the form, but there's no checkbox value, hence it is unchecked
		return 0;
	}
	# we didn't come from the form, hence return default
	return $default;
}
# parameter check to block injection
function check_numeric($val)
{
	if (!is_numeric($val))
	{
		die("Invalid param $val");
	}
}
#######################################################
#
# Tooltip text
#
#######################################################

function tip_text($tag)
{
	if ($tag == "multimap")
	{
		return "Whether to show all the Ensembl proteins mapped to a single gene";
	}
	if ($tag == "genelabels")
	{
		return "Whether to label node using gene name or Ensembl protein name";
	}
	if ($tag == "clst_sel")
	{
		return "For layers 1 and 2, zoom in to the selected factor. Layer 3: show contained factors.";
	}
	if ($tag == "gene_sel")
	{
		return "Zoom in to the selected gene";
	}
	if ($tag == "go_sel")
	{
		return "Show only factors enriched for the selected GO term";
	}
	if ($tag == "kegg_sel")
	{
		return "Show only factors enriched for the selected Kegg term";
	}
	if ($tag == "wt_slider")
	{
		return "Set the minimum link weight to use. Genes with no links meeting this threshold will be hidden.";
	}
	if ($tag == "best_inc")
	{
		return "If checked, genes will only be linked to the factor for which their link weight is highest.";
	}
	if ($tag == "hugo_names")
	{
		return "Use HGNC accepted names, to the extent possible.";
	}
}
#####################################################
#
# Specific form elements
#
###################################################
function run_sel($name,$CRID,$def="")
{
	global $ACCESS;
	$st = dbps("select ID, lbl from clr where hideme=0 order by lbl asc");
	$st->bind_result($ID,$lbl);
	$st->execute();
	while ($st->fetch())
	{
		if (read_access($ID))
		{
			$selected = ($ID == $CRID ? " selected " : "");
			$opts[] = "<option value=$ID $selected>$lbl</option>";
		}
	}
	$st->close();
	$html = "<select name='$name' id='sel_$name'>\n";
	if ($def != "")
	{
		$selected = ($CRID==0 ? " selected " : "");
		$html .= "<option $selected value='0'>$def</option>\n";
	}
	$html .= implode("\n",$opts)."\n</select>\n";
	return $html;
}
function clst_sel($name,$CID,$singlelvl=-1,$defstr="all")
{
	global $CRID, $MaxClstLvl;

	$selected = ($CID == 0 ? " selected " : "");
	$opts[] = "<option value='0' $selected>$defstr</option>";

	$lvlwhere = "";
	if (!is_numeric($singlelvl))
	{
		die ("Bad factor level parameter $singlelvl"); 
	}
	if ($singlelvl >= 0)
	{
		$lvlwhere = " and clst.lvl=$singlelvl ";
	}

	$st = dbps("select ID, lbl, lvl, count(*) as size from clst ".
		" join g2c on g2c.CID=clst.ID ".
		" where clst.CRID=? $lvlwhere ".
		" group by clst.ID ");
	$st->bind_param("i",$CRID);
	$st->bind_result($ID,$lbl,$lvl,$size);
	$st->execute();
	while ($st->fetch())
	{
		$lvl++;
		$size++;
		$selected = ($ID == $CID ? " selected " : "");
		$ngenes = ($lvl == 1 ? "($size genes)" : "");
		$opts[] = "<option value=$ID $selected>Layer$lvl : $lbl $ngenes</option>";
	}
	$st->close();
	# due to the g2c join, the previous only got layer 1
	$st = dbps("select ID, lbl, lvl  from clst ".
		" where clst.CRID=? and lvl > 0 $lvlwhere and clst.lvl <= $MaxClstLvl ".
		" group by clst.ID ");
	$st->bind_param("i",$CRID);
	$st->bind_result($ID,$lbl,$lvl);
	$st->execute();
	while ($st->fetch())
	{
		$lvl++;
		$selected = ($ID == $CID ? " selected " : "");
		$opts[] = "<option value=$ID $selected>Layer$lvl : $lbl </option>";
	}
	$st->close();
	return "<select name='$name' id='sel_$name'>\n".implode("\n",$opts)."\s</select>\n";
}

#####################################################################
#
#	Functions for printing the standard page parts like banners
#
####################################################################

function head_section($title,$xtra="")
{
	echo <<<END
<head>
<title>$title</title>
<meta http-equiv="content-type" content="text/html" charset="utf-8" />
<link rel="stylesheet" type="text/css" href="/corex.css"> 
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
$xtra
</head>

END;
}
##################################################################

function body_start()
{
	echo <<<END
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
END;
}

function body_end()
{
	echo <<<END
		</td>
	</tr>
</table>
</body>
END;
}


##################################################################

function crid_default()
{
	$st = dbps("select min(id) as crid from clr");
	$st->bind_result($crid);
	$st->execute();
	$st->fetch();
	$st->close();
	return $crid;
}

###################################################################
# not using prepared statement since we want to get array of all fields
function load_proj_data(&$data,$crid)
{
	global $DB;
	if (!is_numeric($crid))
	{
		die("sorry");
	}
	$res = $DB->query("select * from clr where id=$crid");
	if (!($data = $res->fetch_assoc()))
	{
		die ("Can't find project $crid\n");
	}
	$numsamp = get_num_samps($data);
	$data["NUMSAMP"] = $numsamp;
}
function project_exists($crid)
{
	global $DB;
	if (!is_numeric($crid))
	{
		die("sorry");
	}
	$res = $DB->query("select * from clr where id=$crid");
	if (!($data = $res->fetch_assoc()))
	{
		return 0;
	}
	return 1;
}

function get_num_samps(&$pdata)
{
	global $DB;
	$numsamp = 0;
	$st = $DB->prepare("select count(*) from samp where dsid=?");
	$st->bind_param("i",$pdata["DSID"]);
	$st->bind_result($numsamp);
	$st->execute();
	$st->fetch();
	$st->close();
	return $numsamp;
}
##########################################################
#
# Login - related things
#
############################################################

function login_init()
{
	global $LOGGED_IN;
	global $USERNAME;
	global $USERID;
	global $ADMIN;
	global $ACCESS;
	
	$LOGGED_IN = false;
	$ADMIN = 0;
	$ACCESS = array();

	# Add public projects to readonly access
	$s = dbps("select id from clr where publc=1");
	$s->bind_result($crid);
	$s->execute();
	while ($s->fetch())
	{
		$ACCESS[$crid] = 0;
	}
	$s->close();

	$msg = "";
	if (isset($_POST["logout"]))
	{
		unset($_SESSION["username"]);
		unset($_SESSION["hash"]);
		$LOGGED_IN = false;
	}
	elseif (isset($_SESSION["username"]))
	{
		$username = $_SESSION["username"];
		$hash = $_SESSION["hash"];
		if (check_login($username,$hash,$USERID,$ADMIN,$ACCESS))
		{
			$LOGGED_IN = true;
			$USERNAME = $username;
		}		
		else
		{
			unset($_SESSION["username"]);
			unset($_SESSION["hash"]);

		}
	}
	elseif (isset($_POST["kibbles"]))
	{
		$username = $_POST["kibbles"];
		$password = $_POST["bits"];
		$hash = hash("sha256",$password);
		
		if (check_login($username,$hash,$USERID,$ADMIN,$ACCESS))
		{
			$_SESSION["username"] = $username;
			$USERNAME = $username;
			$_SESSION["hash"] = $hash;
			$LOGGED_IN = true;
			$cur_link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; # keep get, remove post
			header("Location:$cur_link");
			exit(0);
		}
		else
		{
			$msg = "Invalid username or password";
		}
		
	}
	return $msg;
}
function strip_query_and_reload()
{
	# Pages which process their own form results need to do this after
	# processing so that a later reload by the user doesn't re-submit form data
	$reloadURL = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
	header("Location:$reloadURL");
}
function has_admin_access()
{
	global $ADMIN;
	return ($ADMIN==1);
}

#
# This function checks login creds and also 
# adds user specific access entries
#
function check_login($username,$hash,&$uid,&$admin,&$access)
{
	$uid = 0;	
	$admin = 0;

	# Check login creds and add rest of access projects
	$s = dbps("select UID,uadmin from usrs where usr=? and passwd=? and disab=0");
	$s->bind_param("ss",$username,$hash);
	$s->bind_result($uid,$admin);
	$s->execute();
	$s->fetch();
	$s->close();
	if ($uid != 0)
	{
		$s = dbps("select crid, wrt from access where uid=?");
		$s->bind_param("i",$uid);
		$s->bind_result($crid,$wrt);
		$s->execute();
		while ($s->fetch())
		{
			$access[$crid] = $wrt;
		}
		$s->close();
		return true;
	}
	return false;
}
function check_read_access($crid)
{
	if (!read_access($crid))
	{
		die("No access");
	}
}
function check_write_access($crid)
{
	if (!write_access($crid))
	{
		die("No access");
	}
}
function read_access($crid)
{
	global $ACCESS;
	if (!isset($crid) || $crid == 0)
	{
		return 1;
	}
	if (has_admin_access())
	{
		return 1;
	}
	if (isset($ACCESS[$crid]))
	{
		return 1;
	}
	return 0;
}
function write_access($crid)
{
	global $ACCESS;
	if (has_admin_access())
	{
		return 1;
	}
	if (isset($ACCESS[$crid]))
	{
		if ($ACCESS[$crid] == 1)
		{
			return 1;
		}
	}
	return 0;
}
function require_login()
{
	global $LOGGED_IN;
	if (!$LOGGED_IN)
	{
		die("Must be logged in!");
	}
}
#
# Does the user have write permission to the database
#
function can_load_data()
{
	global $USERID;
	$s = dbps("select addprj from usrs where uid=?");
	$s->bind_param("i",$USERID);
	$s->bind_result($addprj);
	$s->execute();
	if ($s->fetch() == TRUE)
	{
		if ($addprj == 1)
		{
			return 1;
		}
	}	
	return 0;
}


?>

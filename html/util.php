<?php
require_once("db.php");
session_start();

$LOGGED_IN = 0;
$USERNAME = "";
$USERID = 0;
$ADMIN = 0;

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
function checked($val,$def)
{
	if ($val || $def)
	{
		print " checked='true' ";
	}
}
#
# fromForm tells us if the form was actually submitted or the page was called
# with empty query. This only matters if we're trying to make a checkbox
# default to checked. The problem is that when a checkbox is not checked, it
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
	if ($tag == "genechoose")
	{
		return "List shows genes and the clusters they are in based on the current setting ".
			" for minimum link weight";
	}
	if ($tag == "cidchoose")
	{
		return "Choose cluster to highlight. If 'redraw' is pressed, then only the parts of the ".
			" graph below that cluster will be drawn";
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
	$st = dbps("select ID, lbl from clr where hideme=0 order by lbl asc");
	$st->bind_result($ID,$lbl);
	$st->execute();
	while ($st->fetch())
	{
		$selected = ($ID == $CRID ? " selected " : "");
		$opts[] = "<option value=$ID $selected>$lbl</option>";
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
	global $CRID;

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
		$opts[] = "<option value=$ID $selected>Layer$lvl : $lbl ($size genes)</option>";
	}
	$st->close();
	# due to the g2c join, the previous only got layer 1
	$st = dbps("select ID, lbl, lvl  from clst ".
		" where clst.CRID=? and lvl > 0 $lvlwhere ".
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

function head_section($title)
{
	echo <<<END
<head>
<title>$title</title>
<meta http-equiv="content-type" content="text/html" charset="utf-8" />
<link rel="stylesheet" type="text/css" href="corex.css"> 
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
</head>

END;
}
##################################################################

function body_start()
{
	echo <<<END
<body>
<div class="outer">
END;
}

#$page_opts = array("","over","dset","search","how","dl","pub");
#$page_lbls = array("","Overview","Datasets","Search","How-To","Download","Publications");
#$page_pgs = array("welcome.html","overview.html","datasets.html","search.html", "howto.html",
#				"download.html","publications.html");



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
	
	$LOGGED_IN = false;
	$ADMIN = 0;

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
		if (check_login($username,$hash,$USERID,$ADMIN))
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
		
		if (check_login($username,$hash,$USERID,$ADMIN))
		{
			$_SESSION["username"] = $username;
			$USERNAME = $username;
			$_SESSION["hash"] = $hash;
			$LOGGED_IN = true;
			$cur_link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; # keep get, remove post
			#header("Location:$cur_link");
			#exit(0);
		}
		else
		{
			$msg = "Invalid username or password";
		}
		
	}
	return $msg;
}
function has_admin_access()
{
	global $ADMIN;
	return ($ADMIN==1);
}


function check_login($username,$hash,&$uid,&$admin)
{
	$uid = 0;	
	$admin = 0;
	$s = dbps("select UID,uadmin from usrs where usr=? and passwd=?");
	$s->bind_param("ss",$username,$hash);
	$s->bind_result($uid,$admin);
	$s->execute();
	$s->fetch();
	if ($uid != 0)
	{
		return true;
	}
	return false;
}



?>

<?php
#
# Launch deletion and then reload to display progress 
#
require_once("../util.php");
login_init();
require_login();
if (!can_load_data())
{
	die("No permission to delete data!");
}
$CRID = getint("crid",0);
$reload = getint("reload",0);
if (!write_access($CRID))
{
	die("access denied");
}

if ($reload)
{
	if (!project_exists($CRID))
	{
		# delete is done
		header("Location:/manage/manage_projects.php");
	}
}

$pdata = array();
load_proj_data($pdata,$CRID);  # checks project existence
$projname = $pdata["lbl"];

$DATADIR = $_SERVER["COREXDATADIR"];
$SCRIPTDIR = $_SERVER["COREXSCRIPTDIR"];

if (!$reload)
{
	if (delete_in_progress())
	{
		echo("<h5>Another deletion is already in progress!</h5>");
		exit(0);
	}
	$cmd = "/usr/bin/php $SCRIPTDIR/delete_project.php $projname web";
	$fullcmd = "DBUSER=$DBUSER DBPASS=$DBPASS COREXDATADIR=$DATADIR COREXSCRIPTDIR=$SCRIPTDIR $cmd > /dev/null &";
	error_log($fullcmd);
	exec($fullcmd);
	$reloadURL = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
	$reloadURL .= "?crid=$CRID&reload=1";
	header("Location:$reloadURL");
}
$logfile = "$DATADIR/delete.log";
$logtext = file_get_contents($logfile);
?>
<h4>Deleting <?php echo $projname ?></h4>
(Page will automatically reload)
<pre>
<?php echo $logtext ?>
</pre>
<form name="reload">
<input type="hidden" name="reload" value="1">
<input type="hidden" name="crid" value="$CRID">
</form>
<script>
setTimeout(function(){

   location.reload();

},5000);
</script>

<?php

function delete_in_progress()
{
	exec("ps -ef | grep  'delete_project' | grep -v 'vi' | grep -v grep", $output);
	if(empty($output))
	{
		return false;
	}
	else
	{
		return true;
	}
}

?>


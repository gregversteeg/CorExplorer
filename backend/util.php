<?php
require_once("db.php");
$DATADIR = (isset($_SERVER["COREXDATADIR"]) ? $_SERVER["COREXDATADIR"] : getenv("COREXDATADIR"));
$SCRIPTDIR = (isset($_SERVER["COREXSCRIPTDIR"]) ? $_SERVER["COREXSCRIPTDIR"] : getenv("COREXSCRIPTDIR"));


# NOTE THIS FUNCTION DOESN'T PROPERLY HANDLE CSV FILES WITH COMMAS IN THE DATA!!!!
# MUST CHANGE TO USE fgetcsv
########################################################################
#
# Read a CSV or TSV matrix into a double-array, stripping quotes also
# Entries expected to contain letters, nums, -,. or to be numeric
#
function read_matrix(&$matrix,&$numRows, &$numCols,$matrix_file, $header=1)
{
	$nCols = 0;
	$rowNum = 0;
	$colNames = array();
	$rowNames = array();
	$fh = fopen($matrix_file,"r");
	while (($line = fgets($fh)) != null)
	{
		$row = preg_split('/[,\s]+/',trim($line));
		$curCols =  count($row);
		if ($nCols == 0)
		{
			$nCols = $curCols;
		}
		else
		{
			if ($curCols != $nCols)
			{
				die ("Row $rowNum has $curCols; expecting $nCols\n");
			}
		}
		for ($i = 0; $i < $nCols; $i++)
		{
			$row[$i] = preg_replace('/^[\"\']/',"",$row[$i]);
			$row[$i] = preg_replace('/[\"\']$/',"",$row[$i]);
			if (!is_numeric($row[$i]) && preg_match('/[^\w-\.]/',$row[$i]))
			{
				die ("bad matrix entry row:$rowNum, col:$i, value:".$row[$i]."\n");
			}
		}
		if ($header && $rowNum == 0)  # if there's a header, we treat row 0 specially
		{
			for ($i = 0; $i < $nCols; $i++)
			{
				if (isset($colNames[$row[$i]]))
				{
					$oldCol = $colNames[$row[$i]];
					print "Column header for column $i duplicates $oldCol (name:".$row[$i].")\n";
				}
				else
				{
					$colNames[$row[$i]] = $i;
				}
			}
		}
		else
		{
			if (isset($rowNames[$row[0]]))
			{
				$oldRow = $rowNames[$row[0]];
				print "Row name for row $rowNum duplicates $oldRow (name:".$row[0].")\n";
				continue;
			}
			else
			{
				$rowNames[$row[0]] = $rowNum;
			}

		}
		$matrix[] = $row;
		$rowNum++;
	}
	#print "read $rowNum rows, $nCols cols\n";
	$numRows = $rowNum;
	$numCols = $nCols;
}
#
# Not the most efficient but it is easier to understand. 
# It is slow; PHP isn't very good at matrix ops. 
#
function transpose_matrix(&$m1)
{
	$nRows1 = count($m1);
	$nCols1 = count($m1[0]);
	$m2 = array();
	for ($r2 = 0; $r2 < $nCols1; $r2++)
	{
		print "Initializing row $r2......\r";
		$m2[] = array();
		array_fill(0,$nRows1,0);
	}
	print "Initializing done          \n";
	for ($r1 = 0; $r1 < $nRows1; $r1++)
	{
		print "Working on row $r1......\r";
		for ($c1 = 0; $c1 < $nCols1; $c1++)
		{
			$m2[$c1][$r1] = $m1[$r1][$c1];
		}	
		$m1[$r1] = null;
	}
	print "Transpose done.                   \n";
	return $m2;
}
####################################################

function find_proj($name)
{
	$res = dbq("select id from clr where lbl='$name'");
	if ($r = $res->fetch_assoc())
	{
		return $r["id"];
	}
	die ("could not find project $name\n");
}

################################################

function load_proj_data(&$data,$crid)
{
	if (!is_numeric($crid))
	{
		die("sorry");
	}
	$res = dbq("select * from clr where id=$crid");
	if (!($data = $res->fetch_assoc()))
	{
		die ("Can't find project $crid\n");
	}
}

################################################

function load_proj_data2(&$data,$name)
{
	global $DB;
	$crid = find_proj($name);
	$res = $DB->query("select * from clr where id=$crid");
	if (!($data = $res->fetch_assoc()))
	{
		die ("Can't find project $crid\n");
	}
}

##################################################

function check_file($file)
{
	if (!is_file($file))
	{
		die("Can't find file $file\n");
	}
}
#################################################

function yesno($prompt)
{
	$line = trim(readline($prompt."(y/n)?\n"));
	if ($line != "y")
	{
		exit(0);
	}	
}

##########################################################
#
# Incremental clear of expr table, which is not faster
# but is less problematic for mysql than a single delete using DSID. 
#

function clear_expr_by_DS($DSID)
{
	print "Clear expression table for DSID=$DSID\n";
	$samps = array();
	$res = dbq("select ID from samp where DSID=$DSID");
	while ($r = $res->fetch_assoc())
	{
		$samps[] = $r["ID"];
	}
	$nSamps = count($samps);
	foreach ($samps as $SID)
	{
		print "$nSamps      \r";
		dbq("delete from expr where DSID=$DSID and SID=$SID");
		$nSamps--;
	}
}
function run_cmd($cmd, &$retval)
{
	print "$cmd\n";
	system($cmd,$retval);
}
function update_status($crid,$str)
{
	dbq("update clr set projstat='$str' where id=$crid");
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
function strip_gene_suffix(&$gene)
{
	$gene = preg_replace("/\..*/","",$gene);
}
?>


<?php


# NOTE THIS FUNCTION DOESN'T PROPERLY HANDLE CSV FILES WITH COMMAS IN THE DATA!!!!
# MUST CHANGE TO USE fgetcsv
########################################################################
#
# Read a CSV or TSV matrix into a double-array, stripping quotes also
# Entries expected to contain letters, nums, -, or to be numeric
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
			if (!is_numeric($row[$i]) && preg_match('/[^\w-]/',$row[$i]))
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
	print "read $rowNum rows, $nCols cols\n";
	$numRows = $rowNum;
	$numCols = $nCols;
}
#
# Do this the dumb but comprehensible way
#
function transpose_matrix(&$m1)
{
	$nRows1 = count($m1);
	$nCols1 = count($m1[0]);
	$m2 = array();
	for ($r2 = 0; $r2 < $nCols1; $r2++)
	{
		$m2[] = array();
	}
	for ($r1 = 0; $r1 < $nRows1; $r1++)
	{
		for ($c1 = 0; $c1 < $nCols1; $c1++)
		{
			$m2[$c1][$r1] = $m1[$r1][$c1];
		}	
	}
	return $m2;
}
?>


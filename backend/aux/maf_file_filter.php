<?php

#
# Reads all .maf files in current directory (presumed to be 4 files)
# and screens genes to keep those having non-synonymous mutations in
# at least two patients in all 4 files.
#
$files = glob("*.maf");
$num_files = count($files);
if ($num_files != 4)
{
	die ("Expecting 4 maf files. Otherwise need to alter the final code block.");
}
$overall_count = array();
$types = array();
foreach ($files as $file)
{
	#print "$file\n";
	$fh = fopen($file,"r");

	$gcount = array();
	while ( ($line = fgets($fh)) != FALSE)
	{
		$fields = explode("\t",trim($line));
		if (count($fields) < 20)
		{
			continue;
		}
		$type = $fields[8];
		if (!isset($types[$type]))
		{
			$types[$type] = 0;
		}
		$types[$type]++;
		if ($type == "Missense_Mutation" || $type == "Nonsense_Mutation" || 
				$type == "Nonstop_Mutation" ||
				$type == "Frame_Shift_Ins" || $type == "Frame_Shift_Del")
		{
			$gene = $fields[47];
			$samp1 = $fields[15];
			$patient = substr($samp1,0,12);
			#print "$gene\t$patient\n";
			if (!isset($gcount[$gene]))
			{
				$gcount[$gene] = array();
			}
			$gcount[$gene][$patient] = 1;
		}
	}
	#
	# Flag the genes that hit at least 2 patients in this file
	#
	foreach ($gcount as $gene => $arr)
	{
		$count = count($arr);
		if ($count > 1)
		{
			#print "$gene\t$count\n";
			if (!isset($overall_count[$gene]))
			{
				$overall_count[$gene] = 0;
			}
			$overall_count[$gene]++;
		}
	}
}
#
# Now print the ones that were previously flagged in all 4 files
#
$ngenes = 0;
foreach ($overall_count as $gene => $count)
{
	if ($count == 4)
	{
		print "$gene\n";
		$ngenes++;
	}
}
#print "$ngenes\n";

?>

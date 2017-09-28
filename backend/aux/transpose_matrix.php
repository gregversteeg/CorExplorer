<?php
require_once("util.php");

$infile = $argv[1];
$sep = ",";

print "Reading matrix: $infile\n";
$matrix = array();
read_matrix($matrix,$nRows, $nCols,$infile);

print "Read matrix $nRows x $nCols \n";

print "Transposing matrix; could be slow\n";
$matrix = transpose_matrix($matrix);

$outfile = "$infile.transp";
print "Writing $outfile\n";
$fh = fopen($outfile,"w");
for ($r = 0; $r < $nCols; $r++)
{
	$line = implode(",",$matrix[$r]);
	fwrite($fh,"$line\n");
}
fclose($fh);

?>

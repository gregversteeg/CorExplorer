<?php
require_once("util.php");

$Rscript_dir = "/lfs1/corex/Rscripts";

$rscript = "$Rscript_dir/biomart_map_hugo.R";

$proj = $argv[1];
$projdir = $argv[2];

$genelist = "$projdir/genelist.txt";
$genetbl = "$projdir/biomart.gene.tbl";

$pdata = array();
load_proj_data2($pdata,$proj);
$glid = $pdata["GLID"];

print("Map gene names via BioMart....\n");

$name2id = array();
$s = dbps("select id,lbl from glist where glid=$glid");
$s->bind_result($id,$name);
$s->execute();
while ($s->fetch())
{
	strip_gene_suffix($name);
	$name2id[$name] = $id;
}
$s->close();


$genestr = implode("\n",array_keys($name2id))."\n";
file_put_contents($genelist,$genestr);

system("$rscript $projdir");

if (!is_file($genetbl))
{
	die("Biomart gene mapping failed!!\n");
}
$s = dbps("update glist set hugo=?,gtype=?,gsrc=?,descr=? where id=?");
$s->bind_param("ssssi",$hugo,$type,$src,$descr,$gid);
$fh = fopen($genetbl,"r");
fgets($fh);
while ( ($line = fgets($fh)))
{
	$fields = explode("\t",$line);
	$ensp = $fields[0];
	$hugo = $fields[1];
	$src = $fields[2];
	$descr = $fields[3];
	$type = $fields[4];

	strip_gene_suffix($ensp);
	if (isset($name2id[$ensp]))
	{
		$gid = $name2id[$ensp];
		$s->execute();
	}
	else
	{
		die("Biomart map: no ID for $ensp!!\n");
	}
}
$s->close();
	

?>

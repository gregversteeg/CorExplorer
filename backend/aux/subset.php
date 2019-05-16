<?php
require_once("../util.php");

#
# Subset ovca data for test dataset
# Create files:
# weights_layer0,1,2,3.csv, mis_layer0,1,2,3.csv, metadata.tsv,reduced_data.tsv,
# groups.txt, run_details.txt, labels.txt, cont_labels.txt
# 
# Copy in files: labels.txt, cont_labels.txt
#

$crid = 1;
$dsid = 1;

if (file_exists("corex_test"))
{
	die("corex_test exists!\n");
}
mkdir("corex_test");
mkdir("corex_test/output");
mkdir("corex_test/output/text_files");

chdir("corex_test/output/text_files");

$lvl3_factors = array(231);
$lvl2_factors = array(201,205,207,209);
$genes = array();

$uniq = array();
$st = dbps("select cid1 from c2c where cid2 in (205,201,207,209) aNd wt >= .4");
$st->bind_result($cid1);
$st->execute();
while ($st->fetch())
{
	$uniq[$cid1] = 1;
}
$st->close();

$lvl1_factors = array_keys($uniq);

$cinstr = "(".implode(",",$lvl1_factors).")";
$uniq = array();
$st = dbps("select gid,lbl from g2c join glist on glist.id=g2c.gid where cid in $cinstr aNd wt >= .2");
$st->bind_result($gid,$gname);
$st->execute();
while ($st->fetch())
{
	$uniq[$gid] = $gname;
}
$st->close();

$gids = array_keys($uniq);
$NG = count($gids);

$wts = array();
$mis = array();
$st = dbps("select gid, cid, wt, mi from g2c where crid=$crid");
$st->bind_result($gid,$cid,$wt,$mi);
$st->execute();
while ($st->fetch())
{
	if (!isset($wts[$cid]))
	{
		$wts[$cid] = array();
		$mis[$cid] = array();
	}
	$wts[$cid][$gid] = $wt;
	$mis[$cid][$gid] = $mi;
}
$st->close();


$wfile = fopen("weights_layer0.csv","w");
$mfile = fopen("mis_layer0.csv","w");

$firstline = array("factor");
for($i = 0; $i < $NG; $i++)
{
	$gid = $gids[$i];
	$gname = $uniq[$gid];
	$firstline[] = $gname;
}
fwrite($wfile,implode(",",$firstline)."\n");
fwrite($mfile,implode(",",$firstline)."\n");
$N1 = count($lvl1_factors);
for($lbl = 0; $lbl < $N1; $lbl++)
{
	$cid = $lvl1_factors[$lbl];
	$wline = array($lbl);
	$mline = array($lbl);
	for($i = 0; $i < $NG; $i++)
	{
		$gid = $gids[$i];
		$wline[] = (isset($wts[$cid][$gid]) ? $wts[$cid][$gid] : "0.0");
		$mline[] = (isset($mis[$cid][$gid]) ? $mis[$cid][$gid] : "0.0");
	}
	fwrite($wfile,implode(",",$wline)."\n");
	fwrite($mfile,implode(",",$mline)."\n");
}
fclose($wfile);
fclose($mfile);

$wts = array();
$mis = array();
$st = dbps("select cid1, cid2, wt, mi from c2c where crid=$crid");
$st->bind_result($cid1,$cid2,$wt,$mi);
$st->execute();
while ($st->fetch())
{
	if (!isset($wts[$cid1]))
	{
		$wts[$cid1] = array();
		$mis[$cid1] = array();
	}
	$wts[$cid1][$cid2] = $wt;
	$mis[$cid1][$cid2] = $mi;
}
$st->close();

$wfile = fopen("weights_layer1.csv","w");
$mfile = fopen("mis_layer1.csv","w");

$N2 = count($lvl2_factors);
$N1 = count($lvl1_factors);

$firstline = array("factor");
for($i = 0; $i < $N1; $i++)
{
	$firstline[] = $i;
}
fwrite($wfile,implode(",",$firstline)."\n");
fwrite($mfile,implode(",",$firstline)."\n");

for($lbl = 0; $lbl < $N2; $lbl++)
{
	$cid2 = $lvl2_factors[$lbl];
	$wline = array($lbl);
	$mline = array($lbl);
	for($i = 0; $i < $N1; $i++)
	{
		$cid1 = $lvl1_factors[$i];
		$wline[] = (isset($wts[$cid1][$cid2]) ? $wts[$cid1][$cid2] : "0.0");
		$mline[] = (isset($mis[$cid1][$cid2]) ? $mis[$cid1][$cid2] : "0.0");
	}
	fwrite($wfile,implode(",",$wline)."\n");
	fwrite($mfile,implode(",",$mline)."\n");
}
fclose($wfile);
fclose($mfile);

$wfile = fopen("weights_layer2.csv","w");
$mfile = fopen("mis_layer2.csv","w");

$N2 = count($lvl2_factors);
$N3 = count($lvl3_factors);

$firstline = array("factor");
for($i = 0; $i < $N2; $i++)
{
	$firstline[] = $i;
}
fwrite($wfile,implode(",",$firstline)."\n");
fwrite($mfile,implode(",",$firstline)."\n");

for($lbl = 0; $lbl < $N3; $lbl++)
{
	$cid2 = $lvl3_factors[$lbl];
	$wline = array($lbl);
	$mline = array($lbl);
	for($i = 0; $i < $N2; $i++)
	{
		$cid1 = $lvl2_factors[$i];
		$wline[] = (isset($wts[$cid1][$cid2]) ? $wts[$cid1][$cid2] : "0.0");
		$mline[] = (isset($mis[$cid1][$cid2]) ? $mis[$cid1][$cid2] : "0.0");
	}
	fwrite($wfile,implode(",",$wline)."\n");
	fwrite($mfile,implode(",",$mline)."\n");
}
fclose($wfile);
fclose($mfile);

$samps = array();
$lbls = array();
$clbls = array();
$expr = array();
$st = dbps("select id from samp where dsid=$dsid");
$st->bind_result($sid);
$st->execute();
while ($st->fetch())
{
	$samps[] = $sid;
	$lbls[$sid] = array();
	$clbls[$sid] = array();
	$expr[$sid] = array();
}
$st->close();

$st = dbps("select sid,cid, lbl, clbl from lbls where cid in $cinstr");
$st->bind_result($sid,$cid,$lbl,$clbl);
$st->execute();
while ($st->fetch())
{
	$lbls[$sid][$cid] = $lbl;
	$clbls[$sid][$cid] = $clbl;
}
$st->close();

$lfile = fopen("labels.txt","w");
$clfile = fopen("cont_labels.txt","w");
$NS = count($samps);
for ($s = 0; $s < $NS; $s++)
{
	$line = array("samp$s");	
	$cline = array("samp$s");	
	$sid = $samps[$s];
	for ($i = 0; $i < $N1; $i++)
	{
		$cid = $lvl1_factors[$i];
		$line[] = $lbls[$sid][$cid];
		$cline[] = $clbls[$sid][$cid];
	}
	fwrite($lfile,implode(",",$line)."\n");
	fwrite($clfile,implode(",",$cline)."\n");
}
fclose($lfile);
fclose($clfile);

$cid2tc = array();
$st = dbps("select id, tc from clst where crid=$crid");
$st->bind_result($cid,$tc);
$st->execute();
while ($st->fetch())
{
	$cid2tc[$cid] = $tc;
}
$st->close();

$file = fopen("groups.txt","w");
for ($lbl = 0; $lbl < $N1; $lbl++)
{
	$cid = $lvl1_factors[$lbl];
	$tc = $cid2tc[$cid];
	fwrite($file,"Group num: $lbl, TC(X;Y_j): $tc\n");
}
fclose($file);

$params = <<<END
{"0": {"n_repeat": 1, "max_samples": 1000, "ram": 40, "smooth_marginals": true, "max_iter": 300, "eps": 1e-05, "dim_hidden": 3, "n_samples": 379, "n_visible": 3029, "n_cpu": 4, "n_hidden": 200, "marginal_description": "gaussian", "missing_values": 0.0}, "1": {"n_repeat": 5, "max_samples": 10000, "ram": 8.0, "smooth_marginals": false, "max_iter": 100, "eps": 1e-05, "dim_hidden": 3, "n_samples": 379, "n_visible": 200, "n_cpu": 1, "n_hidden": 30, "marginal_description": "discrete", "missing_values": -1}, "2": {"n_repeat": 5, "max_samples": 10000, "ram": 8.0, "smooth_marginals": false, "max_iter": 100, "eps": 1e-05, "dim_hidden": 3, "n_samples": 379, "n_visible": 30, "n_cpu": 1, "n_hidden": 8, "marginal_description": "discrete", "missing_values": -1}, "3": {"n_repeat": 5, "max_samples": 10000, "ram": 8.0, "smooth_marginals": false, "max_iter": 100, "eps": 1e-05, "dim_hidden": 3, "n_samples": 379, "n_visible": 8, "n_cpu": 1, "n_hidden": 1, "marginal_description": "discrete", "missing_values": -1}}
END;
file_put_contents("parameters.json",$params);

$sid2meta = array();
$st = dbps("select sid, dtd,dtlc,stat from sampdt join samp on samp.id=sampdt.sid where samp.dsid=$dsid");
$st->bind_result($sid,$dtd,$dtlc,$stat);
$st->execute();
while ($st->fetch())
{
	$sid2meta[$sid] = array("dtd" => $dtd, "dtlc" => $dtlc, "status" => $stat);
}
$st->close();


############## End text_files ###################

chdir("../..");

$details = <<<END
Source: GDC
Study: OVCA
Processing: FPKM-UQ
Filtering: Columns with zero counts are included but zeros are considered missing.
END;
file_put_contents("run_details.txt",$details);


$file = fopen("metadata.tsv","w");
fwrite($file,"SampleID\tDTD\tDTLC\tStatus\n");
for ($s = 0; $s < $NS; $s++)
{
	$sid = $samps[$s];
	# Turned out 6 samples have no metadata loaded in this dataset...doesn't matter for demo,
	# so we hack around it
	$meta = (isset($sid2meta[$sid]) ? $sid2meta[$sid] : $sid2meta[$sid - 1]);
	$line = array("samp$s");
	$line[] = $meta["dtd"];
	$line[] = $meta["dtlc"];
	$line[] = $meta["status"];
	fwrite($file,implode("\t",$line)."\n");
}
fclose($file);

$st = dbps("select sid,gid,raw from expr where dsid=$dsid ");
$st->bind_result($sid,$gid,$raw);
$st->execute();
while ($st->fetch())
{
	$expr[$sid][$gid] = $raw;
}
$st->close();

$file = fopen("reduced_data.csv","w");
$firstline = array("");
for($i = 0; $i < $NG; $i++)
{
	$gid = $gids[$i];
	$gname = $uniq[$gid];
	$firstline[] = $gname;
}
fwrite($file,implode(",",$firstline)."\n");
for ($s = 0; $s < $NS; $s++)
{
	$line = array("samp$s");	
	$sid = $samps[$s];
	for ($i = 0; $i < $NG; $i++)
	{
		$gid = $gids[$i];
		$line[] = $expr[$sid][$gid];
	}
	fwrite($file,implode(",",$line)."\n");
}
fclose($file);

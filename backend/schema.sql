-- MySQL dump 10.13  Distrib 5.5.55, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: corex
-- ------------------------------------------------------
-- Server version	5.5.55-0ubuntu0.14.04.1-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `access`
--

DROP TABLE IF EXISTS `access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `access` (
  `UID` int(11) NOT NULL,
  `CRID` int(11) NOT NULL,
  `wrt` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c2c`
--

DROP TABLE IF EXISTS `c2c`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c2c` (
  `CID1` int(11) NOT NULL,
  `CID2` int(11) NOT NULL,
  `CRID` int(11) NOT NULL,
  `wt` float NOT NULL,
  `mi` float DEFAULT '0',
  UNIQUE KEY `CID2` (`CID2`,`CID1`),
  KEY `CID1` (`CID1`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clr`
--

DROP TABLE IF EXISTS `clr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clr` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `GLID` int(11) DEFAULT NULL,
  `DSID` int(11) DEFAULT '0',
  `meth` varchar(20) DEFAULT NULL,
  `lbl` varchar(20) DEFAULT NULL,
  `param` text,
  `descr` text,
  `ref` text,
  `projstat` text,
  `dataurl` text,
  `load_dt` datetime NOT NULL,
  `hideme` tinyint(4) DEFAULT '0',
  `publc` tinyint(1) DEFAULT '0',
  `ownedby` int(11) NOT NULL DEFAULT '1',
  `projdir` text,
  `pos_saved` tinyint(4) DEFAULT '0',
  `def_wt` decimal(5,3) DEFAULT '0.000',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `lbl` (`lbl`)
) ENGINE=InnoDB AUTO_INCREMENT=117 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clst`
--

DROP TABLE IF EXISTS `clst`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clst` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `CRID` int(11) NOT NULL,
  `lbl` int(11) NOT NULL,
  `lvl` smallint(6) NOT NULL,
  `survp` float DEFAULT '1',
  `coxp` float DEFAULT '1',
  `tc` float DEFAULT '0',
  `pos_x` float DEFAULT '0',
  `pos_y` float DEFAULT '0',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `CRID` (`CRID`,`lvl`,`lbl`)
) ENGINE=InnoDB AUTO_INCREMENT=24001 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clst2go`
--

DROP TABLE IF EXISTS `clst2go`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clst2go` (
  `CID` int(11) DEFAULT NULL,
  `term` int(11) NOT NULL,
  `pval` float NOT NULL,
  KEY `CID` (`CID`),
  KEY `term` (`term`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clst2kegg`
--

DROP TABLE IF EXISTS `clst2kegg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clst2kegg` (
  `CID` int(11) DEFAULT NULL,
  `term` int(11) NOT NULL,
  `pval` float NOT NULL,
  KEY `CID` (`CID`),
  KEY `term` (`term`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clst_pair`
--

DROP TABLE IF EXISTS `clst_pair`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clst_pair` (
  `CID1` int(11) NOT NULL,
  `CID2` int(11) NOT NULL,
  `coxp` float DEFAULT '1',
  `survp` float DEFAULT '1',
  UNIQUE KEY `CID1` (`CID1`,`CID2`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dset`
--

DROP TABLE IF EXISTS `dset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dset` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `lbl` varchar(50) DEFAULT NULL,
  `expr_type` varchar(20) DEFAULT NULL,
  `descr` text,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `lbl` (`lbl`)
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `e2go`
--

DROP TABLE IF EXISTS `e2go`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e2go` (
  `eterm` int(11) NOT NULL,
  `gterm` int(11) NOT NULL,
  UNIQUE KEY `gterm` (`gterm`,`eterm`),
  KEY `eterm` (`eterm`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eprot`
--

DROP TABLE IF EXISTS `eprot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eprot` (
  `term` int(11) NOT NULL,
  `descr` text,
  UNIQUE KEY `term` (`term`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expr`
--

DROP TABLE IF EXISTS `expr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expr` (
  `GID` int(11) NOT NULL,
  `SID` int(11) NOT NULL,
  `DSID` int(11) NOT NULL,
  `GLID` int(11) DEFAULT NULL,
  `raw` float NOT NULL,
  `logz` float NOT NULL,
  UNIQUE KEY `idx` (`DSID`,`GID`,`SID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `g2c`
--

DROP TABLE IF EXISTS `g2c`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `g2c` (
  `GID` int(11) NOT NULL,
  `CID` int(11) NOT NULL,
  `CRID` int(11) NOT NULL,
  `wt` float NOT NULL,
  `mi` float DEFAULT '0',
  UNIQUE KEY `GID` (`GID`,`CID`),
  KEY `CID` (`CID`),
  KEY `CRID` (`CRID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `g2e`
--

DROP TABLE IF EXISTS `g2e`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `g2e` (
  `GID` int(11) NOT NULL,
  `term` int(11) NOT NULL,
  UNIQUE KEY `GID` (`GID`,`term`),
  KEY `term` (`term`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `glist`
--

DROP TABLE IF EXISTS `glist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `glist` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `GLID` int(11) DEFAULT NULL,
  `lbl` varchar(20) DEFAULT NULL,
  `hugo` varchar(30) DEFAULT NULL,
  `gtype` varchar(30) DEFAULT NULL,
  `gsrc` varchar(30) DEFAULT NULL,
  `descr` text,
  `eterm` int(11) DEFAULT '0',
  `pos_x` float DEFAULT '0',
  `pos_y` float DEFAULT '0',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `lbl` (`GLID`,`lbl`),
  KEY `lblidx` (`lbl`),
  KEY `hugoidx` (`hugo`),
  KEY `idx3` (`eterm`)
) ENGINE=InnoDB AUTO_INCREMENT=1251690 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `glists`
--

DROP TABLE IF EXISTS `glists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `glists` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `descr` text,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gos`
--

DROP TABLE IF EXISTS `gos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gos` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `CRID` int(11) NOT NULL,
  `term` int(11) NOT NULL,
  `descr` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `CRID` (`CRID`,`term`),
  KEY `CRID_2` (`CRID`,`descr`)
) ENGINE=InnoDB AUTO_INCREMENT=1114733 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kegg`
--

DROP TABLE IF EXISTS `kegg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kegg` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `CRID` int(11) DEFAULT NULL,
  `term` int(11) NOT NULL,
  `descr` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `term` (`CRID`,`term`),
  KEY `descr` (`CRID`,`descr`)
) ENGINE=InnoDB AUTO_INCREMENT=27133 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lbls`
--

DROP TABLE IF EXISTS `lbls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lbls` (
  `CID` int(11) NOT NULL,
  `SID` int(11) NOT NULL,
  `lbl` int(11) NOT NULL,
  `clbl` float NOT NULL,
  `risk_strat` int(11) NOT NULL,
  UNIQUE KEY `CID` (`CID`,`SID`),
  KEY `SID` (`SID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pair_lbls`
--

DROP TABLE IF EXISTS `pair_lbls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pair_lbls` (
  `CID1` int(11) NOT NULL,
  `CID2` int(11) NOT NULL,
  `SID` int(11) NOT NULL,
  `risk_strat` int(11) DEFAULT '0',
  UNIQUE KEY `CID1` (`CID1`,`CID2`,`SID`),
  KEY `SID` (`SID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pair_survdt`
--

DROP TABLE IF EXISTS `pair_survdt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pair_survdt` (
  `CID1` int(11) NOT NULL,
  `CID2` int(11) NOT NULL,
  `strat` int(11) DEFAULT '0',
  `dte` int(11) NOT NULL,
  `surv` float NOT NULL,
  KEY `CID1` (`CID1`,`CID2`,`strat`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ppi`
--

DROP TABLE IF EXISTS `ppi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ppi` (
  `ID1` int(11) DEFAULT NULL,
  `ID2` int(11) DEFAULT NULL,
  `score` smallint(6) NOT NULL,
  UNIQUE KEY `ID1` (`ID1`,`ID2`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `samp`
--

DROP TABLE IF EXISTS `samp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `samp` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `lbl` varchar(50) DEFAULT NULL,
  `DSID` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `lbl` (`DSID`,`lbl`)
) ENGINE=InnoDB AUTO_INCREMENT=39520 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sampalias`
--

DROP TABLE IF EXISTS `sampalias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sampalias` (
  `SID` int(11) DEFAULT NULL,
  `lbl` varchar(50) DEFAULT NULL,
  `idx` int(11) NOT NULL,
  UNIQUE KEY `SID` (`SID`,`idx`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sampdt`
--

DROP TABLE IF EXISTS `sampdt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sampdt` (
  `SID` int(11) DEFAULT NULL,
  `dtd` int(11) DEFAULT NULL,
  `dtlc` int(11) DEFAULT NULL,
  `dte` int(11) DEFAULT NULL,
  `stat` tinyint(4) NOT NULL,
  `censor` tinyint(4) NOT NULL,
  `age` smallint(6) DEFAULT '0',
  `sex` char(1) DEFAULT NULL,
  `stage` tinyint(4) DEFAULT NULL,
  `cytored` tinyint(4) DEFAULT NULL,
  `stagestr` varchar(15) DEFAULT NULL,
  `cytoredstr` varchar(30) DEFAULT NULL,
  `statstr` varchar(15) DEFAULT NULL,
  `tstatstr` varchar(30) DEFAULT NULL,
  `fulldata` text,
  UNIQUE KEY `SID` (`SID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `survdt`
--

DROP TABLE IF EXISTS `survdt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `survdt` (
  `CID` int(11) NOT NULL,
  `strat` int(11) DEFAULT '0',
  `dte` int(11) NOT NULL,
  `surv` float NOT NULL,
  KEY `CID` (`CID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `survdt_ov`
--

DROP TABLE IF EXISTS `survdt_ov`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `survdt_ov` (
  `CRID` int(11) NOT NULL,
  `dte` int(11) NOT NULL,
  `surv` float NOT NULL,
  KEY `CRID` (`CRID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usrs`
--

DROP TABLE IF EXISTS `usrs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usrs` (
  `UID` int(11) NOT NULL AUTO_INCREMENT,
  `usr` varchar(30) NOT NULL,
  `passwd` varchar(100) NOT NULL,
  `descr` tinytext,
  `uadmin` tinyint(1) DEFAULT '0',
  `adddate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addprj` tinyint(1) DEFAULT '1',
  `disab` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`UID`),
  UNIQUE KEY `usr` (`usr`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2019-05-07 20:29:36

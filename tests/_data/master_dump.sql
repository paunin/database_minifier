-- MySQL dump 10.13  Distrib 5.6.23, for Linux (x86_64)
--
-- Host: localhost    Database: test_master
-- ------------------------------------------------------
-- Server version	5.6.23

/*!40101 SET @OLD_CHARACTER_SET_CLIENT = @@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS = @@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION = @@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE = @@TIME_ZONE */;
/*!40103 SET TIME_ZONE = '+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS = @@UNIQUE_CHECKS, UNIQUE_CHECKS = 0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS = 0 */;
/*!40101 SET @OLD_SQL_MODE = @@SQL_MODE, SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES = @@SQL_NOTES, SQL_NOTES = 0 */;

--
-- Table structure for table `car`
--

DROP TABLE IF EXISTS `car`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `car` (
  `idcar`  INT(11) NOT NULL AUTO_INCREMENT,
  `carcol` VARCHAR(45)      DEFAULT NULL,
  PRIMARY KEY (`idcar`)
)
  ENGINE = InnoDB
  AUTO_INCREMENT = 6
  DEFAULT CHARSET = latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `car`
--

LOCK TABLES `car` WRITE;
/*!40000 ALTER TABLE `car` DISABLE KEYS */;
INSERT INTO `car` VALUES (1, 'Fiat'), (2, 'Mercedes'), (3, 'Juke'), (4, 'Renault'), (5, 'Jaguar');
/*!40000 ALTER TABLE `car` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `city`
--

DROP TABLE IF EXISTS `city`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `city` (
  `idcity`      INT(11)     NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(45)          DEFAULT NULL,
  `code`        VARCHAR(45)          DEFAULT 'city_default_code',
  `population`  INT(11)              DEFAULT NULL,
  `country_id`  INT(11)     NOT NULL,
  `country_id2` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`idcity`),
  KEY `fk_city_country_idx` (`country_id`, `country_id2`),
  CONSTRAINT `fk_city_country` FOREIGN KEY (`country_id`, `country_id2`) REFERENCES `country` (`id`, `id2`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
)
  ENGINE = InnoDB
  AUTO_INCREMENT = 5
  DEFAULT CHARSET = latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `city`
--

LOCK TABLES `city` WRITE;
/*!40000 ALTER TABLE `city` DISABLE KEYS */;
INSERT INTO `city` VALUES (1, 'Moscow', 'MSK', 10, 1, 'compose_pk'), (2, 'New York', 'NY', 9, 2, 'compose_pk'),
  (3, 'Madrid', 'M', 8, 3, 'compose_pk'), (4, 'Reterburg', 'SPB', 7, 1, 'compose_pk');
/*!40000 ALTER TABLE `city` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `country`
--

DROP TABLE IF EXISTS `country`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `country` (
  `id`         INT(11)     NOT NULL AUTO_INCREMENT,
  `id2`        VARCHAR(45) NOT NULL DEFAULT 'compose_pk',
  `name`       VARCHAR(45)          DEFAULT NULL,
  `population` INT(11)              DEFAULT NULL,
  `created`    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `code`       FLOAT       NOT NULL,
  PRIMARY KEY (`id`, `id2`),
  UNIQUE KEY `code_UNIQUE` (`code`)
)
  ENGINE = InnoDB
  AUTO_INCREMENT = 4
  DEFAULT CHARSET = latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `country`
--

LOCK TABLES `country` WRITE;
/*!40000 ALTER TABLE `country` DISABLE KEYS */;
INSERT INTO `country` VALUES (1, 'compose_pk', 'Russia', 10, '2015-04-20 03:33:50', 1.1),
  (2, 'compose_pk', 'USA', 9, '2015-04-20 03:34:07', 2.2), (3, 'compose_pk', 'Spain', 8, '2015-04-20 03:34:25', 3.3);
/*!40000 ALTER TABLE `country` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `house`
--

DROP TABLE IF EXISTS `house`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `house` (
  `idhouse`         INT(11)     NOT NULL AUTO_INCREMENT,
  `number`          VARCHAR(45) NOT NULL,
  `street_idstreet` INT(11)     NOT NULL,
  PRIMARY KEY (`idhouse`),
  KEY `fk_house_street1_idx` (`street_idstreet`),
  CONSTRAINT `fk_house_street1` FOREIGN KEY (`street_idstreet`) REFERENCES `street` (`idstreet`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
)
  ENGINE = InnoDB
  AUTO_INCREMENT = 10
  DEFAULT CHARSET = latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `house`
--

LOCK TABLES `house` WRITE;
/*!40000 ALTER TABLE `house` DISABLE KEYS */;
INSERT INTO `house`
VALUES (1, '1A', 1), (2, '1B', 1), (3, '2', 1), (4, '5', 1), (5, '11', 2), (6, '12', 2), (7, '31', 3), (8, 'AA', 3),
  (9, '5', 5);
/*!40000 ALTER TABLE `house` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `persone`
--

DROP TABLE IF EXISTS `persone`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `persone` (
  `idpersone`         INT(11)     NOT NULL AUTO_INCREMENT,
  `name`              VARCHAR(45)          DEFAULT NULL,
  `country_id`        INT(11)     NOT NULL,
  `country_id2`       VARCHAR(45) NOT NULL,
  `persone_idpersone` INT(11)              DEFAULT NULL,
  `man`               TINYINT(1)           DEFAULT '1',
  PRIMARY KEY (`idpersone`),
  KEY `fk_persone_country1_idx` (`country_id`, `country_id2`),
  KEY `fk_persone_persone1_idx` (`persone_idpersone`),
  CONSTRAINT `fk_persone_country1` FOREIGN KEY (`country_id`, `country_id2`) REFERENCES `country` (`id`, `id2`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_persone_persone1` FOREIGN KEY (`persone_idpersone`) REFERENCES `persone` (`idpersone`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
)
  ENGINE = InnoDB
  AUTO_INCREMENT = 6
  DEFAULT CHARSET = latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `persone`
--

LOCK TABLES `persone` WRITE;
/*!40000 ALTER TABLE `persone` DISABLE KEYS */;
INSERT INTO `persone` VALUES (1, 'DMITRIY', 1, 'compose_pk', NULL, 1), (2, 'ALICE', 1, 'compose_pk', 1, 0),
  (3, 'KATE', 2, 'compose_pk', 2, 0), (4, 'BOB', 3, 'compose_pk', NULL, 1), (5, 'BOBBY', 3, 'compose_pk', 4, 0);
/*!40000 ALTER TABLE `persone` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `persone_has_car`
--

DROP TABLE IF EXISTS `persone_has_car`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `persone_has_car` (
  `persone_idpersone` INT(11) NOT NULL,
  `car_idcar`         INT(11) NOT NULL,
  PRIMARY KEY (`persone_idpersone`, `car_idcar`),
  KEY `fk_persone_has_car_car1_idx` (`car_idcar`),
  KEY `fk_persone_has_car_persone1_idx` (`persone_idpersone`),
  CONSTRAINT `fk_persone_has_car_car1` FOREIGN KEY (`car_idcar`) REFERENCES `car` (`idcar`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_persone_has_car_persone1` FOREIGN KEY (`persone_idpersone`) REFERENCES `persone` (`idpersone`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
)
  ENGINE = InnoDB
  DEFAULT CHARSET = latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `persone_has_car`
--

LOCK TABLES `persone_has_car` WRITE;
/*!40000 ALTER TABLE `persone_has_car` DISABLE KEYS */;
INSERT INTO `persone_has_car` VALUES (1, 1), (1, 2), (2, 3), (2, 4), (3, 4), (4, 4), (5, 5);
/*!40000 ALTER TABLE `persone_has_car` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `persone_has_house`
--

DROP TABLE IF EXISTS `persone_has_house`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `persone_has_house` (
  `persone_idpersone` INT(11) NOT NULL,
  `house_idhouse`     INT(11) NOT NULL,
  PRIMARY KEY (`persone_idpersone`, `house_idhouse`),
  KEY `fk_persone_has_house_house1_idx` (`house_idhouse`),
  KEY `fk_persone_has_house_persone1_idx` (`persone_idpersone`),
  CONSTRAINT `fk_persone_has_house_house1` FOREIGN KEY (`house_idhouse`) REFERENCES `house` (`idhouse`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_persone_has_house_persone1` FOREIGN KEY (`persone_idpersone`) REFERENCES `persone` (`idpersone`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
)
  ENGINE = InnoDB
  DEFAULT CHARSET = latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `persone_has_house`
--

LOCK TABLES `persone_has_house` WRITE;
/*!40000 ALTER TABLE `persone_has_house` DISABLE KEYS */;
INSERT INTO `persone_has_house` VALUES (1, 1), (1, 2), (2, 3), (2, 4), (3, 4), (4, 4), (5, 5), (5, 6), (2, 6), (2, 8), (3, 9), (4, 9), (5, 9);
/*!40000 ALTER TABLE `persone_has_house` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `street`
--

DROP TABLE IF EXISTS `street`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `street` (
  `idstreet`    INT(11)     NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(45)          DEFAULT NULL,
  `code`        VARCHAR(45)          DEFAULT NULL,
  `city_idcity` INT(11)     NOT NULL,
  `country_id`  INT(11)     NOT NULL,
  `country_id2` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`idstreet`),
  KEY `fk_street_city1_idx` (`city_idcity`),
  KEY `fk_street_country1_idx` (`country_id`, `country_id2`),
  CONSTRAINT `fk_street_city1` FOREIGN KEY (`city_idcity`) REFERENCES `city` (`idcity`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_street_country1` FOREIGN KEY (`country_id`, `country_id2`) REFERENCES `country` (`id`, `id2`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
)
  ENGINE = InnoDB
  AUTO_INCREMENT = 6
  DEFAULT CHARSET = latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `street`
--

LOCK TABLES `street` WRITE;
/*!40000 ALTER TABLE `street` DISABLE KEYS */;
INSERT INTO `street` VALUES (1, 'MIRA', 'M', 1, 1, 'compose_pk'), (2, 'LENINA', 'L', 1, 1, 'compose_pk'),
  (3, 'MAINSTREET', 'MS', 2, 2, 'compose_pk'), (4, 'NOT_MAIN street', 'NMS', 2, 2, 'compose_pk'),
  (5, 'FOO STREET', 'M', 2, 2, 'compose_pk');
/*!40000 ALTER TABLE `street` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE = @OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE = @OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS = @OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT = @OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS = @OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION = @OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES = @OLD_SQL_NOTES */;

-- Dump completed on 2015-04-20  3:58:27

delimiter $$

CREATE DATABASE `wiki` /*!40100 DEFAULT CHARACTER SET utf8 */$$

use wiki$$

CREATE TABLE `user` (
  `username` varchar(45) NOT NULL,
  `mail` varchar(45) DEFAULT NULL,
  `groups` varchar(45) DEFAULT NULL,
  `charid` bigint(20) NOT NULL,
  `charname` varchar(45) NOT NULL,
  `corpid` bigint(20) NOT NULL,
  `corpname` varchar(45) NOT NULL,
  `allianceid` bigint(20) DEFAULT NULL,
  `alliancename` varchar(45) DEFAULT NULL,
  `authtoken` varchar(45) NOT NULL,
  `authcreated` bigint(20) NOT NULL,
  `authlast` bigint(20) NOT NULL,
  PRIMARY KEY (`charid`),
  UNIQUE KEY `username_UNIQUE` (`username`),
  UNIQUE KEY `charid_UNIQUE` (`charid`),
  UNIQUE KEY `charname_UNIQUE` (`charname`),
  UNIQUE KEY `authtoken_UNIQUE` (`authtoken`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8$$

CREATE TABLE `session` (
  `sessionid` varchar(45) NOT NULL,
  `charid` bigint(20) NOT NULL,
  `created` bigint(20) NOT NULL,
  PRIMARY KEY (`sessionid`),
  UNIQUE KEY `sessionid_UNIQUE` (`sessionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8$$

CREATE TABLE `grp` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `grp` varchar(45) NOT NULL,
  `criteria` varchar(45) NOT NULL,
  `comment` varchar(45) DEFAULT NULL,	
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8$$

INSERT INTO `grp` (`grp`, `criteria`) VALUES ('admin', 'tag_wiki.admin');$$


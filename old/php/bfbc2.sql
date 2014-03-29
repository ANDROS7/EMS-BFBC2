-- phpMyAdmin SQL Dump
-- version 3.5.1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Apr 04, 2013 at 05:47 PM
-- Server version: 5.5.24-log
-- PHP Version: 5.3.13

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `bfbc2`
--

-- --------------------------------------------------------

--
-- Table structure for table `friends`
--

CREATE TABLE IF NOT EXISTS `friends` (
  `name` varchar(255) NOT NULL,
  `id` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `ip` varchar(255) NOT NULL,
  `members` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `friends`
--

INSERT INTO `friends` (`name`, `id`, `owner`, `ip`, `members`) VALUES
('myfriend', '1000000', 'friend', '127.0.0.1', '1');

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE IF NOT EXISTS `games` (
  `game_id` int(11) NOT NULL AUTO_INCREMENT,
  `lobby_id` int(11) NOT NULL,
  `game_hn` varchar(255) DEFAULT NULL,
  `game_hu` int(11) DEFAULT NULL,
  `game_n` varchar(255) NOT NULL,
  `game_i` char(255) NOT NULL,
  `game_p` int(11) NOT NULL,
  `game_j` varchar(11) DEFAULT NULL,
  `game_v` varchar(255) NOT NULL,
  `game_jp` int(11) DEFAULT NULL,
  `game_qp` int(11) NOT NULL,
  `game_ap` int(11) NOT NULL,
  `game_mp` int(11) NOT NULL,
  `game_f` int(11) DEFAULT NULL,
  `game_nf` int(11) DEFAULT NULL,
  `game_pl` varchar(255) NOT NULL,
  `game_pw` int(11) NOT NULL,
  `game_hardcore` int(11) NOT NULL,
  `game_hasPassword` int(11) NOT NULL,
  `game_punkbuster` int(11) NOT NULL,
  `game_level` varchar(255) NOT NULL,
  `game_sguid` varchar(255) NOT NULL,
  `game_time` varchar(255) NOT NULL,
  `game_hash` varchar(255) NOT NULL,
  `game_region` varchar(255) NOT NULL,
  `game_public` int(11) NOT NULL,
  `B-U-EA` int(11) NOT NULL,
  `B-U-Provider` varchar(255) NOT NULL,
  `B-U-QueueLength` int(11) NOT NULL,
  `B-U-Softcore` int(11) NOT NULL,
  `B-U-gameMod` varchar(255) NOT NULL,
  `B-U-gamemode` varchar(255) NOT NULL,
  `game_elo` int(11) NOT NULL,
  `game_version` varchar(11) NOT NULL,
  `game_numObservers` int(11) NOT NULL,
  `game_maxObservers` int(11) NOT NULL,
  `server_online` int(10) NOT NULL,
  `B-U-PunkBusterVersion` varchar(50) NOT NULL,
  `UGID` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`game_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;


-- --------------------------------------------------------

--
-- Table structure for table `gdet`
--

CREATE TABLE IF NOT EXISTS `gdet` (
  `LID` int(11) NOT NULL,
  `GID` int(11) NOT NULL,
  `AutoBalance` int(11) NOT NULL,
  `BannerUrl` varchar(255) DEFAULT NULL,
  `Crosshair` int(11) NOT NULL,
  `FriendlyFire` varchar(255) NOT NULL,
  `KillCam` int(11) NOT NULL,
  `Minimap` int(11) NOT NULL,
  `MinimapSpotting` int(11) NOT NULL,
  `ServerDescription0` varchar(255) DEFAULT NULL,
  `ServerDescriptionCount` int(11) NOT NULL,
  `ThirdPersonVehicleCameras` int(11) DEFAULT NULL,
  `ThreeDSpotting` int(11) NOT NULL,
  `pdat00` varchar(255) NOT NULL,
  `pdat01` varchar(255) NOT NULL,
  `pdat02` varchar(255) NOT NULL,
  `pdat03` varchar(255) NOT NULL,
  `pdat04` varchar(255) NOT NULL,
  `pdat05` varchar(255) NOT NULL,
  `pdat06` varchar(255) NOT NULL,
  `pdat07` varchar(255) NOT NULL,
  `pdat08` varchar(255) NOT NULL,
  `pdat09` varchar(255) NOT NULL,
  `pdat10` varchar(255) NOT NULL,
  `pdat11` varchar(255) NOT NULL,
  `pdat12` varchar(255) NOT NULL,
  `pdat13` varchar(255) NOT NULL,
  `pdat14` varchar(255) NOT NULL,
  `pdat15` varchar(255) NOT NULL,
  `pdat16` varchar(255) NOT NULL,
  `pdat17` varchar(255) NOT NULL,
  `pdat18` varchar(255) NOT NULL,
  `pdat19` varchar(255) NOT NULL,
  `pdat20` varchar(255) NOT NULL,
  `pdat21` varchar(255) NOT NULL,
  `pdat22` varchar(255) NOT NULL,
  `pdat23` varchar(255) NOT NULL,
  `pdat24` varchar(255) NOT NULL,
  `pdat25` varchar(255) NOT NULL,
  `pdat26` varchar(255) NOT NULL,
  `pdat27` varchar(255) NOT NULL,
  `pdat28` varchar(255) NOT NULL,
  `pdat29` varchar(255) NOT NULL,
  `pdat30` varchar(255) NOT NULL,
  `pdat31` varchar(255) NOT NULL,
  PRIMARY KEY (`GID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data for table `gdet`
--

INSERT INTO `gdet` (`LID`, `GID`, `AutoBalance`, `BannerUrl`, `Crosshair`, `FriendlyFire`, `KillCam`, `Minimap`, `MinimapSpotting`, `ServerDescription0`, `ServerDescriptionCount`, `ThirdPersonVehicleCameras`, `ThreeDSpotting`, `pdat00`, `pdat01`, `pdat02`, `pdat03`, `pdat04`, `pdat05`, `pdat06`, `pdat07`, `pdat08`, `pdat09`, `pdat10`, `pdat11`, `pdat12`, `pdat13`, `pdat14`, `pdat15`, `pdat16`, `pdat17`, `pdat18`, `pdat19`, `pdat20`, `pdat21`, `pdat22`, `pdat23`, `pdat24`, `pdat25`, `pdat26`, `pdat27`, `pdat28`, `pdat29`, `pdat30`, `pdat31`) VALUES
(257, 1, 1, '', 1, '0.0000', 1, 1, 1, '', 0, 1, 1, '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0'),
(257, 2, 1, '', 1, '0.0000', 1, 1, 1, '', 0, 1, 1, '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0'),
(257, 3, 1, '', 1, '0.0000', 1, 1, 1, '', 0, 1, 1, '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0'),
(257, 4, 1, '', 1, '0.0000', 1, 1, 1, '', 0, 1, 1, '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0'),
(257, 5, 1, '', 1, '0.0000', 1, 1, 1, '', 0, 1, 1, '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0'),
(257, 6, 1, '', 1, '0.0000', 1, 1, 1, '', 0, 1, 1, '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0'),
(257, 7, 1, '', 1, '0.0000', 1, 1, 1, '', 0, 1, 1, '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0'),
(257, 8, 1, '', 1, '0.0000', 1, 1, 1, '', 0, 1, 1, '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0'),
(257, 9, 1, '', 1, '0.0000', 1, 1, 1, '', 0, 1, 1, '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0'),
(257, 10, 1, '', 1, '0.0000', 1, 1, 1, '', 0, 1, 1, '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0');

-- --------------------------------------------------------

--
-- Table structure for table `lobbies`
--

CREATE TABLE IF NOT EXISTS `lobbies` (
  `lobby_id` int(11) NOT NULL,
  `lobby_name` varchar(255) NOT NULL,
  `lobby_locale` varchar(255) NOT NULL,
  `lobby_num_games` int(11) NOT NULL,
  `lobby_max_games` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `lobbies`
--

INSERT INTO `lobbies` (`lobby_id`, `lobby_name`, `lobby_locale`, `lobby_num_games`, `lobby_max_games`) VALUES
(257, 'bfbc2PC01', 'en_US', 1599, 10000);

-- --------------------------------------------------------

--
-- Table structure for table `personas`
--

CREATE TABLE IF NOT EXISTS `personas` (
  `persona_id` int(11) NOT NULL AUTO_INCREMENT,
  `persona_name` varchar(255) NOT NULL,
  `ip` varchar(255) NOT NULL,
  `user_id` int(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `persona_lkey` varchar(255) DEFAULT NULL,
  `persona_lastLogin` datetime NOT NULL,
  `persona_online` varchar(255) NOT NULL,
  PRIMARY KEY (`persona_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=100000;

--
-- Dumping data for table `personas`
--

INSERT INTO `personas` (`persona_id`, `persona_name`, `ip`, `user_id`, `email`, `persona_lkey`, `persona_lastLogin`, `persona_online`) VALUES
(4, 'test', '', 4, 'test@test.com', NULL, '2012-04-16 22:28:49', '0'),
(1, 'bfbc2.server.p', '', 1, 'bfbc2.server.pc@ea.com', 'T61V9il34oQP1OAvQyaNR4IUWjN.', '2013-04-04 20:27:44', '1'),
(5, 'player', '0', 4, 'test@test.com', NULL, '2012-02-21 17:57:06', '0'),
(2, 'bfbc.server.ps', '0', 2, 'bfbc.server.ps3@ea.com', 'F9JAzOqqOQj0C9RyTHIX66jOz3c.', '2012-03-25 14:21:54', '1'),
(3, 'bfbc.server.xe', '0', 3, 'bfbc.server.xenon@ea.com', 'dRqakGaxUH5vhbYNlqjbAudVboJ.', '2012-03-25 14:24:39', '1');

-- --------------------------------------------------------

--
-- Table structure for table `ping_sites`
--

CREATE TABLE IF NOT EXISTS `ping_sites` (
  `ping_site_addr` varchar(255) DEFAULT NULL,
  `ping_site_type` int(255) DEFAULT NULL,
  `ping_site_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `ping_sites`
--

INSERT INTO `ping_sites` (`ping_site_addr`, `ping_site_type`, `ping_site_name`) VALUES
('127.0.0.1', 0, 'gva'),
('127.0.0.1', 0, 'nrt'),
('127.0.0.1', 0, 'iad'),
('127.0.0.1', 0, 'sjc');

-- --------------------------------------------------------

--
-- Table structure for table `stats`
--

CREATE TABLE IF NOT EXISTS `stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `persona_id` int(11) NOT NULL,
  `persona_stats` blob,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;


--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_nuid` varchar(255) NOT NULL,
  `user_password` varchar(255) NOT NULL,
  `macaddr` varchar(255) DEFAULT NULL,
  `user_lkey` varchar(255) DEFAULT NULL,
  `user_displayName` varchar(255) NOT NULL,
  `user_online` varchar(255) NOT NULL,
  `user_lastLogin` datetime NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=100000;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `user_nuid`, `user_password`, `macaddr`, `user_lkey`, `user_displayName`, `user_online`, `user_lastLogin`) VALUES
(4, 'test@test.com', 'test', '', NULL, 'test', '0', '2012-04-16 22:28:46'),
(1, 'bfbc2.server.pc@ea.com', 'Che6rEPA', '', 'IntmWc2LKwJh8XggwzUxVYjtNfL.', 'bfbc2.server.p', '1', '2013-04-04 20:27:44'),
(2, 'bfbc.server.ps3@ea.com', 'zAmeH7bR', '', 'F9JAzOqqOQj0C9RyTHIX66jOz3c.', 'bfbc2.server.ps', '1', '2012-03-25 14:21:54'),
(3, 'bfbc.server.xenon@ea.com', 'B8ApRavE', '', 'dRqakGaxUH5vhbYNlqjbAudVboJ.', 'bfbc2.server.xe', '1', '2012-03-25 14:24:39');


--
-- Table structure for table `dogtags`
--

CREATE TABLE IF NOT EXISTS `dogtags` (
  `persona_id` int(11) NOT NULL,
  `key` int(11) NOT NULL,
  `value` varchar(50) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `banned`
--

CREATE TABLE IF NOT EXISTS `banned` (
  `id` bigint(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(15) DEFAULT NULL,
  `active` tinyint(1) DEFAULT NULL,
  `user_nuid` varchar(255) DEFAULT NULL COMMENT 'user mail',
  `type` varchar(1) NOT NULL COMMENT '''c'' -client, ''s'' - server, ''x'' - all',
  `reason` varchar(255) DEFAULT NULL,
  `nb_bans` int(11) DEFAULT NULL COMMENT 'number of bans',
  `created` datetime NOT NULL,
  `expire` datetime DEFAULT NULL COMMENT 'null - never',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;



/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

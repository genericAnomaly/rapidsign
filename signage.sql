-- phpMyAdmin SQL Dump
-- version 4.0.9
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Apr 15, 2016 at 06:01 PM
-- Server version: 5.6.14
-- PHP Version: 5.5.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `signage`
--

-- --------------------------------------------------------

--
-- Table structure for table `content`
--

CREATE TABLE IF NOT EXISTS `content` (
  `content_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `content_name` text NOT NULL,
  `content_src` text NOT NULL,
  PRIMARY KEY (`content_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=41 ;

-- --------------------------------------------------------

--
-- Table structure for table `durations`
--

CREATE TABLE IF NOT EXISTS `durations` (
  `duration_id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `duration_name` text NOT NULL,
  `date_start` date NOT NULL,
  `date_end` date NOT NULL,
  PRIMARY KEY (`duration_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=8829 ;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE IF NOT EXISTS `feedback` (
  `feedback_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_upn` text NOT NULL,
  `feedback_comment` text NOT NULL,
  PRIMARY KEY (`feedback_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `players`
--

CREATE TABLE IF NOT EXISTS `players` (
  `player_id` int(11) NOT NULL AUTO_INCREMENT,
  `player_name` text NOT NULL,
  `player_shortname` varchar(127) NOT NULL,
  `player_css` text NOT NULL,
  `astra_guid` text NOT NULL,
  `weather_coords` text NOT NULL,
  PRIMARY KEY (`player_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=7 ;

-- --------------------------------------------------------

--
-- Table structure for table `privs_global`
--

CREATE TABLE IF NOT EXISTS `privs_global` (
  `user_id` int(11) NOT NULL,
  `perm_superuser` tinyint(1) NOT NULL DEFAULT '0',
  `perm_content` int(11) NOT NULL DEFAULT '0',
  `perm_player_read` int(11) NOT NULL DEFAULT '0',
  `perm_player_write` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `privs_players`
--

CREATE TABLE IF NOT EXISTS `privs_players` (
  `player_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `perm_player_read` int(11) NOT NULL DEFAULT '0',
  `perm_player_write` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`player_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `player_id` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `ldap_upn` varchar(127) NOT NULL,
  `user_role` int(11) NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `ldap_upn` (`ldap_upn`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=8 ;

-- --------------------------------------------------------

--
-- Table structure for table `weather_cache`
--

CREATE TABLE IF NOT EXISTS `weather_cache` (
  `lat` float NOT NULL,
  `lon` float NOT NULL,
  `fresh` datetime NOT NULL,
  `json` text NOT NULL,
  PRIMARY KEY (`lat`,`lon`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `privs_players`
--
ALTER TABLE `privs_players`
  ADD CONSTRAINT `privs_players_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `privs_players_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`player_id`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

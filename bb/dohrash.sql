-- phpMyAdmin SQL Dump
-- version 3.5.2.2
-- http://www.phpmyadmin.net
--
-- Хост: 127.0.0.1
-- Время создания: Май 31 2014 г., 09:08
-- Версия сервера: 5.5.27-log
-- Версия PHP: 5.4.6

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- База данных: `tiktak`
--

-- --------------------------------------------------------

--
-- Структура таблицы `dohrash`
--

CREATE TABLE IF NOT EXISTS `dohrash` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kassa` varchar(64) NOT NULL,
  `operation` varchar(64) NOT NULL,
  `type` varchar(64) NOT NULL,
  `amount` decimal(11,1) NOT NULL,
  `info` text NOT NULL,
  `cr_time` int(11) NOT NULL,
  `cr_who_id` int(11) NOT NULL,
  `ch_time` int(11) NOT NULL,
  `ch_who_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

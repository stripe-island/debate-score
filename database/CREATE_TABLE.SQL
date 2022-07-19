-- phpMyAdmin SQL Dump
-- version 4.4.15.10
-- https://www.phpmyadmin.net
--
-- Host: *************************
-- Generation Time: 2022 年 7 月 19 日 15:40
-- サーバのバージョン： 5.7.27
-- PHP Version: 5.4.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `debatescore_database`
--
CREATE DATABASE IF NOT EXISTS `debatescore_database` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `debatescore_database`;

-- --------------------------------------------------------

--
-- テーブルの構造 `classes`
--

CREATE TABLE IF NOT EXISTS `classes` (
  `id` tinyint(3) unsigned NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- テーブルの構造 `entries`
--

CREATE TABLE IF NOT EXISTS `entries` (
  `id` smallint(5) unsigned NOT NULL,
  `team_name` varchar(255) NOT NULL,
  `class_id` tinyint(3) unsigned NOT NULL,
  `event_id` smallint(5) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- テーブルの構造 `events`
--

CREATE TABLE IF NOT EXISTS `events` (
  `id` smallint(5) unsigned NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- テーブルの構造 `games`
--

CREATE TABLE IF NOT EXISTS `games` (
  `id` smallint(5) unsigned NOT NULL,
  `room_name` varchar(255) NOT NULL,
  `entry_id_affirmative` smallint(5) unsigned NOT NULL,
  `entry_id_negative` smallint(5) unsigned NOT NULL,
  `event_id` smallint(5) unsigned NOT NULL,
  `schedule_id` smallint(5) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- テーブルの構造 `results`
--

CREATE TABLE IF NOT EXISTS `results` (
  `game_id` smallint(5) unsigned NOT NULL,
  `judge_name` varchar(255) NOT NULL,
  `vote_affirmative` tinyint(3) unsigned NOT NULL,
  `constructive_affirmative` tinyint(3) unsigned NOT NULL,
  `questions_affirmative` tinyint(3) unsigned NOT NULL,
  `answers_affirmative` tinyint(3) unsigned NOT NULL,
  `rebuttal1_affirmative` tinyint(3) unsigned NOT NULL,
  `rebuttal2_affirmative` tinyint(3) unsigned NOT NULL,
  `manner_affirmative` tinyint(4) NOT NULL,
  `vote_negative` tinyint(3) unsigned NOT NULL,
  `constructive_negative` tinyint(3) unsigned NOT NULL,
  `questions_negative` tinyint(3) unsigned NOT NULL,
  `answers_negative` tinyint(3) unsigned NOT NULL,
  `rebuttal1_negative` tinyint(3) unsigned NOT NULL,
  `rebuttal2_negative` tinyint(3) unsigned NOT NULL,
  `manner_negative` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- テーブルの構造 `schedules`
--

CREATE TABLE IF NOT EXISTS `schedules` (
  `id` smallint(5) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `date` bigint(20) unsigned NOT NULL,
  `event_id` smallint(5) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- テーブルの構造 `staffs`
--

CREATE TABLE IF NOT EXISTS `staffs` (
  `id` smallint(5) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `password` char(8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `entries`
--
ALTER TABLE `entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `team_name` (`team_name`,`event_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `id` (`id`,`event_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_name` (`room_name`,`schedule_id`),
  ADD KEY `entry_id_affirmative` (`entry_id_affirmative`,`event_id`),
  ADD KEY `entry_id_negative` (`entry_id_negative`,`event_id`),
  ADD KEY `event_id` (`event_id`,`schedule_id`);

--
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD UNIQUE KEY `game_id_2` (`game_id`,`judge_name`),
  ADD KEY `game_id` (`game_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`,`event_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `id` (`id`,`event_id`);

--
-- Indexes for table `staffs`
--
ALTER TABLE `staffs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `entries`
--
ALTER TABLE `entries`
  MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `staffs`
--
ALTER TABLE `staffs`
  MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `entries`
--
ALTER TABLE `entries`
  ADD CONSTRAINT `entries_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `entries_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- テーブルの制約 `games`
--
ALTER TABLE `games`
  ADD CONSTRAINT `games_ibfk_1` FOREIGN KEY (`entry_id_affirmative`, `event_id`) REFERENCES `entries` (`id`, `event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `games_ibfk_2` FOREIGN KEY (`entry_id_negative`, `event_id`) REFERENCES `entries` (`id`, `event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `games_ibfk_3` FOREIGN KEY (`event_id`, `schedule_id`) REFERENCES `schedules` (`event_id`, `id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- テーブルの制約 `results`
--
ALTER TABLE `results`
  ADD CONSTRAINT `results_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- テーブルの制約 `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

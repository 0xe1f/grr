
-- MySQL dump 10.13  Distrib 5.6.10, for osx10.7 (x86_64)
--
-- Host: localhost    Database: grr
-- ------------------------------------------------------
-- Server version	5.6.10

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
-- Table structure for table `articles`
--

DROP TABLE IF EXISTS `articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_id` int(11) NOT NULL,
  `guid` varchar(512) NOT NULL,
  `link_url` varchar(256) NOT NULL,
  `title` varchar(256) NOT NULL,
  `author` varchar(128) DEFAULT NULL,
  `summary` varchar(512) NOT NULL,
  `content` text NOT NULL,
  `published` datetime DEFAULT NULL,
  `crawled` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_feed_id` (`feed_id`),
  KEY `crawled` (`crawled`),
  CONSTRAINT `fk_feed_id` FOREIGN KEY (`feed_id`) REFERENCES `feeds` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_logins`
--

DROP TABLE IF EXISTS `failed_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_logins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_ip` varchar(46) NOT NULL,
  `attempt_time` datetime NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_failed_logins_user_id` (`user_id`),
  KEY `source_ip` (`source_ip`),
  CONSTRAINT `fk_failed_logins_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `feed_folder_trees`
--

DROP TABLE IF EXISTS `feed_folder_trees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feed_folder_trees` (
  `ancestor_id` int(11) NOT NULL,
  `descendant_id` int(11) NOT NULL,
  `distance` int(11) NOT NULL,
  PRIMARY KEY (`ancestor_id`,`descendant_id`),
  KEY `fk_uagh_descendant_id` (`descendant_id`),
  CONSTRAINT `fk_feed_folder_trees_ancestor_id` FOREIGN KEY (`ancestor_id`) REFERENCES `feed_folders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_feed_folder_trees_descendant_id` FOREIGN KEY (`descendant_id`) REFERENCES `feed_folders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `feed_folders`
--

DROP TABLE IF EXISTS `feed_folders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feed_folders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `feed_id` int(11) DEFAULT NULL,
  `title` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_feed_id_user_id` (`feed_id`,`user_id`),
  KEY `fk_feed_folders_user_id` (`user_id`),
  KEY `fk_feed_folders_feed_id` (`feed_id`),
  CONSTRAINT `fk_feed_folders_feed_id` FOREIGN KEY (`feed_id`) REFERENCES `feeds` (`id`),
  CONSTRAINT `fk_feed_folders_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `feed_links`
--

DROP TABLE IF EXISTS `feed_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feed_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_id` int(11) NOT NULL,
  `url` varchar(512) NOT NULL,
  `url_hash` char(40) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_feed_links_feed_id` (`feed_id`),
  KEY `ix_url_hash` (`url_hash`),
  CONSTRAINT `fk_feed_links_feed_id` FOREIGN KEY (`feed_id`) REFERENCES `feeds` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `feeds`
--

DROP TABLE IF EXISTS `feeds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feeds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_url` varchar(512) NOT NULL,
  `feed_hash` varchar(40) NOT NULL,
  `html_url` varchar(512) DEFAULT NULL,
  `title` varchar(512) NOT NULL,
  `summary` varchar(512) DEFAULT NULL,
  `added` datetime NOT NULL,
  `last_built` datetime DEFAULT NULL,
  `last_updated` datetime NOT NULL,
  `next_update` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_feeds_feed_hash` (`feed_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `metadata`
--

DROP TABLE IF EXISTS `metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `metadata` (
  `schema_version` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(8) NOT NULL,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `hash` char(64) CHARACTER SET latin1 NOT NULL,
  `source_ip` varchar(46) CHARACTER SET latin1 NOT NULL,
  `created` datetime NOT NULL,
  `voided` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_sessions_user_id` (`user_id`),
  CONSTRAINT `fk_sessions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staged_articles`
--

DROP TABLE IF EXISTS `staged_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staged_articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_id` int(11) NOT NULL,
  `guid` varchar(512) NOT NULL,
  `link_url` varchar(256) NOT NULL,
  `title` varchar(256) NOT NULL,
  `author` varchar(128) DEFAULT NULL,
  `summary` varchar(512) NOT NULL,
  `content` text NOT NULL,
  `published` datetime DEFAULT NULL,
  `crawled` datetime NOT NULL,
  `stage_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_staged_articles_stage_id` (`stage_id`),
  CONSTRAINT `fk_staged_articles_stage_id` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staged_feeds`
--

DROP TABLE IF EXISTS `staged_feeds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staged_feeds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `feed_hash` char(40) NOT NULL,
  `feed_url` varchar(512) NOT NULL,
  `html_url` varchar(512) NOT NULL,
  `title` varchar(512) NOT NULL,
  `summary` varchar(512) DEFAULT NULL,
  `last_built` datetime DEFAULT NULL,
  `last_updated` datetime NOT NULL,
  `next_update` datetime DEFAULT NULL,
  `stage_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_staged_feeds_stage_id` (`stage_id`),
  CONSTRAINT `fk_staged_feeds_stage_id` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stages`
--

DROP TABLE IF EXISTS `stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `started` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_stages_user_id` (`user_id`),
  CONSTRAINT `fk_stages_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_article_tags`
--

DROP TABLE IF EXISTS `user_article_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_article_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_article_id` int(11) NOT NULL,
  `tag` varchar(256) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_user_article_tags_article_id` (`user_article_id`),
  CONSTRAINT `fk_user_article_tags_article_id` FOREIGN KEY (`user_article_id`) REFERENCES `user_articles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_articles`
--

DROP TABLE IF EXISTS `user_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `feed_id` int(11) NOT NULL,
  `is_unread` tinyint(1) NOT NULL DEFAULT '1',
  `is_starred` tinyint(1) NOT NULL DEFAULT '0',
  `is_liked` tinyint(1) NOT NULL DEFAULT '0',
  `read_time` datetime DEFAULT NULL,
  `star_time` datetime DEFAULT NULL,
  `like_time` datetime DEFAULT NULL,
  `tags` varchar(1024) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ik_user_id_article_id` (`user_id`,`article_id`),
  KEY `fk_user_articles_user_id` (`user_id`),
  KEY `fk_user_articles_article_id` (`article_id`),
  KEY `fk_user_articles_feed_id` (`feed_id`),
  CONSTRAINT `fk_user_articles_feed_id` FOREIGN KEY (`feed_id`) REFERENCES `feeds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_articles_article_id` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_articles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL,
  `password` varchar(60) DEFAULT NULL,
  `email_address` varchar(128) NOT NULL,
  `open_id_identity` varchar(512) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `welcome_token_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `ux_users_email_address` (`email_address`),
  KEY `fk_users_role_id` (`role_id`),
  KEY `fk_users_welcome_token_id` (`welcome_token_id`),
  CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_users_welcome_token_id` FOREIGN KEY (`welcome_token_id`) REFERENCES `welcome_tokens` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `welcome_tokens`
--

DROP TABLE IF EXISTS `welcome_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `welcome_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token_hash` char(40) NOT NULL,
  `description` varchar(128) NOT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `revoked_by_user_id` int(11) DEFAULT NULL,
  `created` datetime NOT NULL,
  `claimed` datetime DEFAULT NULL,
  `revoked` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_welcome_tokens_created_by_user_id` (`created_by_user_id`),
  KEY `fk_welcome_tokens_revoked_by_user_id` (`revoked_by_user_id`),
  KEY `token_hash` (`token_hash`),
  CONSTRAINT `fk_welcome_tokens_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_welcome_tokens_revoked_by_user_id` FOREIGN KEY (`revoked_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-07-28  1:13:58
-- MySQL dump 10.13  Distrib 5.6.10, for osx10.7 (x86_64)
--
-- Host: localhost    Database: grr
-- ------------------------------------------------------
-- Server version	5.6.10

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
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'user','User'),(2,'admin','Administrator');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `metadata`
--

LOCK TABLES `metadata` WRITE;
/*!40000 ALTER TABLE `metadata` DISABLE KEYS */;
INSERT INTO `metadata` VALUES (13);
/*!40000 ALTER TABLE `metadata` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-07-28  1:13:58

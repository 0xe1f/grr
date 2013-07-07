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

INSERT INTO feed_links SELECT null, id, html_url, SHA1(html_url) FROM feeds;

CREATE TABLE `stages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `started` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_stages_user_id` (`user_id`),
  CONSTRAINT `fk_stages_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

TRUNCATE TABLE staged_feeds;
ALTER TABLE staged_feeds DROP COLUMN staged;
ALTER TABLE staged_feeds ADD COLUMN stage_id INT NOT NULL;
ALTER TABLE staged_feeds ADD CONSTRAINT fk_staged_feeds_stage_id FOREIGN KEY (stage_id) REFERENCES stages(id) ON DELETE CASCADE;

TRUNCATE TABLE staged_articles;
ALTER TABLE staged_articles DROP COLUMN staged;
ALTER TABLE staged_articles ADD COLUMN stage_id INT NOT NULL;
ALTER TABLE staged_articles ADD CONSTRAINT fk_staged_articles_stage_id FOREIGN KEY (stage_id) REFERENCES stages(id) ON DELETE CASCADE;

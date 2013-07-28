ALTER TABLE articles ADD INDEX (crawled);
ALTER TABLE feed_folders ADD UNIQUE KEY ux_feed_id_user_id (feed_id, user_id);                                                                                                 

ALTER TABLE user_articles ADD COLUMN feed_id INT NOT NULL AFTER article_id;
UPDATE user_articles ua JOIN articles a ON ua.article_id = a.id SET ua.feed_id = a.feed_id;
ALTER TABLE user_articles ADD CONSTRAINT fk_user_articles_feed_id FOREIGN KEY (feed_id) REFERENCES feeds(id);

UPDATE metadata SET schema_version = 14;

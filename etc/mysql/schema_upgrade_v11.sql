ALTER TABLE user_articles ADD COLUMN tags VARCHAR(1024) NOT NULL;
UPDATE user_articles ua JOIN (SELECT user_article_id id, GROUP_CONCAT(tag SEPARATOR ',') tags FROM user_article_tags GROUP BY user_article_id) uatags ON ua.id = uatags.id SET ua.tags = uatags.tags;

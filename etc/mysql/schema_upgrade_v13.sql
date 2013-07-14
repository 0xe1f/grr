ALTER TABLE staged_articles CHANGE COLUMN published published DATETIME DEFAULT NULL;

UPDATE metadata SET schema_version = 13;
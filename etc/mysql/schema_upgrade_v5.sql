ALTER TABLE feed_folder_trees DROP FOREIGN KEY fk_uagh_descendant_id;
ALTER TABLE feed_folder_trees ADD CONSTRAINT fk_feed_folder_trees_descendant_id FOREIGN KEY (descendant_id) REFERENCES feed_folders(id) ON DELETE CASCADE;
ALTER TABLE feed_folder_trees DROP FOREIGN KEY fk_uagh_ancestor_id;
ALTER TABLE feed_folder_trees ADD CONSTRAINT fk_feed_folder_trees_ancestor_id FOREIGN KEY (ancestor_id) REFERENCES feed_folders(id) ON DELETE CASCADE;

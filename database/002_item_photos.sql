-- Proposed addition to lost_found_schema.sql (needs the database team's OK).
-- Group decision: up to 5 photos per item. The schema only had one
-- photo_storage_key column on items, so photos get their own table.
-- items.photo_storage_key is kept and holds the FIRST photo (the thumbnail
-- shown in search results), so nothing in the existing schema has to change.

CREATE TABLE item_photos (
  photo_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id BIGINT UNSIGNED NOT NULL,
  storage_key VARCHAR(500) NOT NULL COMMENT 'File name inside uploads/item_photos/; never a public URL',
  sort_order TINYINT UNSIGNED NOT NULL COMMENT '1 = first photo / thumbnail ... 5',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (photo_id),
  UNIQUE KEY uq_item_photos_order (item_id, sort_order),
  UNIQUE KEY uq_item_photos_key (storage_key),
  CONSTRAINT fk_item_photos_item FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
  CONSTRAINT ck_item_photos_order CHECK (sort_order BETWEEN 1 AND 5)
) ENGINE=InnoDB;

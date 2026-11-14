ALTER TABLE {PREFIX}product_reviews ADD COLUMN photos TEXT NULL COMMENT 'Photo paths under /assets/images/, one per line, shown under the review text' AFTER content;

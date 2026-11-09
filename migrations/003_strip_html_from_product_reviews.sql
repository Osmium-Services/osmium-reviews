UPDATE {PREFIX}product_reviews
SET content = TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(content, '</p><p>', '\n\n'), '</p>\n<p>', '\n\n'), '<p>', ''), '</p>', ''), '<br>', '\n'))
WHERE content LIKE '%<p>%';

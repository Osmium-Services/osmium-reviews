SELECT id, name, contact, rating, content, created_at, verified_purchase
FROM {TABLE}
WHERE product_id = :product_id AND is_published = 1 AND deleted_at IS NULL
ORDER BY created_at ASC

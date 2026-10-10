SELECT R.id, R.name, R.rating, P.model_name AS product_title
FROM {TABLE} AS R
LEFT JOIN {PREFIX}products AS P ON P.id = R.product_id
WHERE R.is_published = 0 AND R.deleted_at IS NULL
ORDER BY R.submitted_at DESC
LIMIT :limit

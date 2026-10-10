SELECT R.id, R.name, R.rating, R.deleted_at, P.model_name AS product_title
FROM {TABLE} AS R
LEFT JOIN {PREFIX}products AS P ON P.id = R.product_id
WHERE R.deleted_at IS NOT NULL
ORDER BY R.deleted_at DESC

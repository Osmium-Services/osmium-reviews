SELECT R.id, R.name, R.rating, R.submitted_at, R.updated_at, P.model_name AS product_title
FROM {TABLE} AS R
LEFT JOIN {PREFIX}products AS P ON P.id = R.product_id
WHERE R.deleted_at IS NULL
  AND (R.submitted_at >= NOW() - INTERVAL 24 HOUR OR R.updated_at >= NOW() - INTERVAL 24 HOUR)
ORDER BY GREATEST(COALESCE(R.updated_at, R.submitted_at), R.submitted_at) DESC, R.id DESC
LIMIT 10

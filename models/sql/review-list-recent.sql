SELECT R.id, R.name, R.rating, R.submitted_at, R.updated_at, R.deleted_at, P.model_name AS product_title, CL.user_name AS changed_by, CU.avatar AS changed_by_avatar
FROM {TABLE} AS R
LEFT JOIN {PREFIX}products AS P ON P.id = R.product_id
LEFT JOIN {PREFIX}changelog AS CL ON CL.id = (SELECT MAX(id) FROM {PREFIX}changelog WHERE record_type = 'review' AND record_id = R.id)
LEFT JOIN {PREFIX}users AS CU ON CU.id = CL.user_id
WHERE (R.submitted_at >= NOW() - INTERVAL 24 HOUR OR R.updated_at >= NOW() - INTERVAL 24 HOUR OR R.deleted_at >= NOW() - INTERVAL 24 HOUR)
ORDER BY GREATEST(COALESCE(R.updated_at, R.submitted_at), R.submitted_at, COALESCE(R.deleted_at, R.submitted_at)) DESC, R.id DESC
LIMIT 10

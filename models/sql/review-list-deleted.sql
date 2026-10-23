SELECT R.id, R.name, R.rating, R.deleted_at, P.model_name AS product_title, CL.user_name AS changed_by, CU.avatar AS changed_by_avatar
FROM {TABLE} AS R
LEFT JOIN {PREFIX}products AS P ON P.id = R.product_id
LEFT JOIN {PREFIX}changelog AS CL ON CL.id = (SELECT MAX(id) FROM {PREFIX}changelog WHERE record_type = 'review' AND record_id = R.id)
LEFT JOIN {PREFIX}users AS CU ON CU.id = CL.user_id
WHERE R.deleted_at IS NOT NULL
ORDER BY R.deleted_at DESC

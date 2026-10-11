SELECT R.id, R.product_id, R.name, R.contact, R.rating, R.verified_purchase, R.submitted_at,
       P.model_name, B.name AS brand_name, P.deleted_at AS product_deleted_at
FROM {TABLE} AS R
LEFT JOIN {PREFIX}products AS P ON P.id = R.product_id
LEFT JOIN {PREFIX}productbrands AS B ON B.id = P.brand_id
WHERE R.is_published = 0 AND R.deleted_at IS NULL
ORDER BY R.submitted_at ASC, R.id ASC
LIMIT :limit

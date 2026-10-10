SELECT R.id, R.product_id, R.name, R.contact, R.rating, R.content, R.submitted_at, R.is_published, R.verified_purchase,
       P.model_name AS product_title, P.reference AS product_sku
FROM {TABLE} AS R
LEFT JOIN {PREFIX}products AS P ON P.id = R.product_id
WHERE R.deleted_at IS NULL
ORDER BY R.submitted_at DESC

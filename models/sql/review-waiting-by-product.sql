SELECT R.product_id, P.model_name, B.name AS brand_name, P.deleted_at AS product_deleted_at,
       COUNT(*) AS waiting, MIN(R.submitted_at) AS oldest, ROUND(AVG(R.rating), 1) AS average_rating,
       SUM(R.verified_purchase = 1) AS verified
FROM {TABLE} AS R
LEFT JOIN {PREFIX}products AS P ON P.id = R.product_id
LEFT JOIN {PREFIX}productbrands AS B ON B.id = P.brand_id
WHERE R.is_published = 0 AND R.deleted_at IS NULL
GROUP BY R.product_id, P.model_name, B.name, P.deleted_at
ORDER BY waiting DESC, oldest ASC

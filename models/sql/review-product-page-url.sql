SELECT PG.url
FROM {PREFIX}products_pages AS PP
INNER JOIN {PREFIX}pages AS PG ON PG.id = PP.page_id
WHERE PP.product_id = :product_id AND NOT PP.is_qr AND PP.deleted_at IS NULL AND PG.deleted_at IS NULL
LIMIT 1

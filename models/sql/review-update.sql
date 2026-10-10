UPDATE {TABLE}
SET product_id = :product_id, name = :name, contact = :contact, rating = :rating,
    content = :content, is_published = :is_published
WHERE id = :id

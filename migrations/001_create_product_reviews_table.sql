CREATE TABLE IF NOT EXISTS {PREFIX}product_reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL DEFAULT 'Anonymous',
    contact VARCHAR(255) DEFAULT NULL COMMENT 'Reviewer email, never shown publicly',
    ip_address VARCHAR(45) DEFAULT NULL,
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
    content TEXT DEFAULT NULL COMMENT 'Plain text, escaped when shown',
    is_published TINYINT NOT NULL DEFAULT 0,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_product_reviews_product (product_id, is_published, deleted_at),
    KEY idx_product_reviews_ip (ip_address, submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

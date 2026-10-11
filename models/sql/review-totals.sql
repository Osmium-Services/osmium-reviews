SELECT
    COALESCE(SUM(is_published = 0), 0) AS waiting,
    MIN(CASE WHEN is_published = 0 THEN submitted_at END) AS oldest_waiting,
    COALESCE(SUM(is_published = 1), 0) AS published,
    ROUND(AVG(CASE WHEN is_published = 1 THEN rating END), 1) AS average_rating
FROM {TABLE}
WHERE deleted_at IS NULL

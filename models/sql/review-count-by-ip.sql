SELECT COUNT(*) AS count FROM {TABLE}
WHERE ip_address = :ip_address AND submitted_at >= NOW() - INTERVAL :minutes MINUTE

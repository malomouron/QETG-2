-- Add privacy-conscious daily aggregates to existing QETG databases.
-- Back up first and select the intended QETG database before running this file.
-- No IP address, user-agent, session token, or visitor identifier is stored.
-- Safe to apply more than once; existing aggregate data is preserved.
CREATE TABLE IF NOT EXISTS quiz_daily_traffic (
    daily DATE NOT NULL,
    page_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
    visitor_sessions BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (daily)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

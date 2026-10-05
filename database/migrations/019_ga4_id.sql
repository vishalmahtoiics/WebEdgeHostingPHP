-- Google Analytics 4 for the public website (Settings → SEO; an ID already entered there is kept).
INSERT INTO settings (`key`, `value`, updated_at) VALUES ('seo.ga4_id', 'G-7VC3EQQFG7', NOW())
ON DUPLICATE KEY UPDATE `value` = IF(`value` IS NULL OR TRIM(`value`) = '', VALUES(`value`), `value`), updated_at = IF(`value` = VALUES(`value`), NOW(), updated_at);

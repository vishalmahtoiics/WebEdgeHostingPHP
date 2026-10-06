-- SSL dates set by a Super Admin (kept until cleared, instead of the live certificate's dates),
-- email storage set by a Super Admin (total per email domain and size per mailbox),
-- and customers' requests for more email storage.
ALTER TABLE ssl_checks
    ADD COLUMN manual TINYINT(1) NOT NULL DEFAULT 0 AFTER provider_status,
    ADD COLUMN manual_note VARCHAR(255) NULL AFTER manual,
    ADD COLUMN manual_by INT UNSIGNED NULL AFTER manual_note,
    ADD COLUMN manual_at DATETIME NULL AFTER manual_by;

ALTER TABLE email_domains
    ADD COLUMN storage_limit_mb INT UNSIGNED NULL AFTER max_mailboxes,
    ADD COLUMN default_quota_mb INT UNSIGNED NULL AFTER storage_limit_mb;

CREATE TABLE IF NOT EXISTS email_upgrade_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_domain_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    message VARCHAR(1000) NULL,
    status ENUM('open','done','dismissed') NOT NULL DEFAULT 'open',
    handled_by INT UNSIGNED NULL,
    handled_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    KEY idx_eur_status (status),
    KEY idx_eur_domain (email_domain_id),
    CONSTRAINT fk_eur_domain FOREIGN KEY (email_domain_id) REFERENCES email_domains(id) ON DELETE CASCADE,
    CONSTRAINT fk_eur_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

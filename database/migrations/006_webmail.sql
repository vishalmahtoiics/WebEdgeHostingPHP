-- Built-in webmail: per-domain mail server settings (NULL = use the defaults
-- under Settings → Webmail) and per-mailbox preferences.
ALTER TABLE email_domains
    ADD COLUMN imap_host VARCHAR(253) NULL AFTER synced_at,
    ADD COLUMN imap_port SMALLINT UNSIGNED NULL AFTER imap_host,
    ADD COLUMN imap_security ENUM('ssl','tls','none') NULL AFTER imap_port,
    ADD COLUMN smtp_host VARCHAR(253) NULL AFTER imap_security,
    ADD COLUMN smtp_port SMALLINT UNSIGNED NULL AFTER smtp_host,
    ADD COLUMN smtp_security ENUM('ssl','tls','none') NULL AFTER smtp_port;

CREATE TABLE webmail_prefs (
    email VARCHAR(320) NOT NULL PRIMARY KEY,
    display_name VARCHAR(150) NULL,
    signature TEXT NULL,
    page_size SMALLINT UNSIGNED NOT NULL DEFAULT 50,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

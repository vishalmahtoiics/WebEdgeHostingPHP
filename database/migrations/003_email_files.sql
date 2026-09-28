-- WebEdge Solution: email domains, mailboxes, aliases; website file access

CREATE TABLE email_domains (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(253) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NULL,
    domain_id INT UNSIGNED NULL,
    provider_id INT UNSIGNED NULL,
    provider_resource_id INT UNSIGNED NULL,
    external_order_id VARCHAR(64) NULL,
    source ENUM('manual','discovered') NOT NULL DEFAULT 'manual',
    status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
    suspend_reason VARCHAR(255) NULL,
    verified_at DATETIME NULL,
    verification_note VARCHAR(500) NULL,
    synced_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_email_domains_customer (customer_id),
    CONSTRAINT fk_email_domains_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_email_domains_domain FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE SET NULL,
    CONSTRAINT fk_email_domains_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mailboxes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_domain_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NULL,
    local_part VARCHAR(64) NOT NULL,
    address VARCHAR(320) NOT NULL UNIQUE,
    display_name VARCHAR(150) NULL,
    external_id VARCHAR(64) NULL,
    quota_mb INT UNSIGNED NULL,
    storage_used_mb INT UNSIGNED NULL,
    status ENUM('active','disabled','suspended') NOT NULL DEFAULT 'active',
    status_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_mailboxes_customer (customer_id),
    CONSTRAINT fk_mailboxes_domain FOREIGN KEY (email_domain_id) REFERENCES email_domains(id) ON DELETE CASCADE,
    CONSTRAINT fk_mailboxes_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_aliases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_domain_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NULL,
    local_part VARCHAR(64) NOT NULL,
    address VARCHAR(320) NOT NULL UNIQUE,
    destination VARCHAR(320) NOT NULL,
    external_id VARCHAR(64) NULL,
    external_mailbox_id VARCHAR(64) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_aliases_destination (destination),
    KEY idx_aliases_customer (customer_id),
    CONSTRAINT fk_aliases_domain FOREIGN KEY (email_domain_id) REFERENCES email_domains(id) ON DELETE CASCADE,
    CONSTRAINT fk_aliases_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE websites
    ADD COLUMN file_access ENUM('none','local','ftp') NOT NULL DEFAULT 'none' AFTER root_directory,
    ADD COLUMN file_root VARCHAR(255) NULL AFTER file_access,
    ADD COLUMN ftp_host VARCHAR(255) NULL AFTER file_root,
    ADD COLUMN ftp_port SMALLINT UNSIGNED NULL AFTER ftp_host,
    ADD COLUMN ftp_user VARCHAR(100) NULL AFTER ftp_port,
    ADD COLUMN ftp_password_enc TEXT NULL AFTER ftp_user,
    ADD COLUMN ftp_tls TINYINT(1) NOT NULL DEFAULT 1 AFTER ftp_password_enc;

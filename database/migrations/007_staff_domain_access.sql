-- Staff (admin) users can be limited to selected domains.
ALTER TABLE users ADD COLUMN domain_scope ENUM('all','selected') NOT NULL DEFAULT 'all' AFTER role_id;

CREATE TABLE admin_domain_access (
    user_id INT UNSIGNED NOT NULL,
    domain VARCHAR(253) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, domain),
    CONSTRAINT fk_admin_domain_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-customer limits set by an admin (NULL = use the plan's limit).
ALTER TABLE customers
    ADD COLUMN max_mailboxes INT UNSIGNED NULL,
    ADD COLUMN max_email_aliases INT UNSIGNED NULL;

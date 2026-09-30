-- Node.js web apps deployed from a zip upload or a Git repository.
CREATE TABLE IF NOT EXISTS nodejs_apps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    website_id INT UNSIGNED NOT NULL,
    source ENUM('archive', 'git') NULL,
    git_host ENUM('github', 'gitlab') NULL,
    git_owner VARCHAR(190) NULL,
    git_repo VARCHAR(190) NULL,
    git_branch VARCHAR(190) NULL,
    git_token TEXT NULL,
    auto_deploy TINYINT(1) NOT NULL DEFAULT 0,
    webhook_secret VARCHAR(64) NOT NULL,
    settings TEXT NULL,
    pending_archive VARCHAR(255) NULL,
    pending_detected TEXT NULL,
    pending_source VARCHAR(20) NULL,
    pending_detail VARCHAR(255) NULL,
    env_vars TEXT NULL,
    last_build_uuid VARCHAR(64) NULL,
    last_build_state VARCHAR(20) NULL,
    last_deployed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_nodejs_apps_website (website_id),
    CONSTRAINT fk_nodejs_apps_website FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nodejs_builds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    website_id INT UNSIGNED NOT NULL,
    build_uuid VARCHAR(64) NOT NULL,
    source VARCHAR(20) NOT NULL,
    detail VARCHAR(255) NULL,
    state VARCHAR(20) NOT NULL DEFAULT 'pending',
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_nodejs_builds_site (website_id, id),
    UNIQUE KEY uq_nodejs_builds_uuid (build_uuid),
    CONSTRAINT fk_nodejs_builds_website FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-customer switch (NULL = follow the global setting).
ALTER TABLE customers ADD COLUMN allow_nodejs TINYINT(1) NULL;

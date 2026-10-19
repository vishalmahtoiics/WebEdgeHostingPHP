-- Deploy PHP / static websites from a GitHub or GitLab repository into the website's files.
CREATE TABLE IF NOT EXISTS git_deploys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    website_id INT UNSIGNED NOT NULL UNIQUE,
    git_host VARCHAR(10) NOT NULL,
    git_owner VARCHAR(200) NOT NULL,
    git_repo VARCHAR(100) NOT NULL,
    git_branch VARCHAR(190) NOT NULL DEFAULT 'main',
    git_token TEXT NULL,
    source_dir VARCHAR(255) NOT NULL DEFAULT '',
    target_dir VARCHAR(255) NOT NULL DEFAULT '',
    keep_files TEXT NULL,
    delete_removed TINYINT(1) NOT NULL DEFAULT 0,
    auto_deploy TINYINT(1) NOT NULL DEFAULT 0,
    webhook_secret VARCHAR(64) NOT NULL,
    manifest MEDIUMTEXT NULL,
    last_status VARCHAR(10) NULL,
    last_deployed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_git_deploys_website FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS git_deploy_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    website_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    trigger_type VARCHAR(10) NOT NULL,
    ref VARCHAR(255) NULL,
    status VARCHAR(10) NOT NULL,
    files_uploaded INT UNSIGNED NOT NULL DEFAULT 0,
    files_deleted INT UNSIGNED NOT NULL DEFAULT 0,
    files_kept INT UNSIGNED NOT NULL DEFAULT 0,
    message VARCHAR(1000) NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    KEY idx_gdr_website (website_id, id),
    CONSTRAINT fk_gdr_website FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

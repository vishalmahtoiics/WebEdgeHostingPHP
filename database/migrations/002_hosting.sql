-- WebEdge Solution: hosting providers, discovered resources, domains, DNS, websites, databases, SSL

CREATE TABLE providers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    driver VARCHAR(30) NOT NULL,
    credentials TEXT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('unknown','ok','error') NOT NULL DEFAULT 'unknown',
    last_error VARCHAR(500) NULL,
    last_checked_at DATETIME NULL,
    last_sync_at DATETIME NULL,
    sync_summary VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_resources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id INT UNSIGNED NOT NULL,
    type VARCHAR(30) NOT NULL,
    external_id VARCHAR(191) NOT NULL,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(40) NULL,
    parent VARCHAR(191) NULL,
    meta TEXT NULL,
    is_missing TINYINT(1) NOT NULL DEFAULT 0,
    local_type VARCHAR(30) NULL,
    local_id INT UNSIGNED NULL,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    UNIQUE KEY uq_resource (provider_id, type, external_id),
    KEY idx_resource_type (type, local_id),
    KEY idx_resource_local (local_type, local_id),
    CONSTRAINT fk_resource_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE domains (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(253) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NULL,
    provider_id INT UNSIGNED NULL,
    provider_resource_id INT UNSIGNED NULL,
    source ENUM('manual','discovered') NOT NULL DEFAULT 'manual',
    status ENUM('active','pending','suspended','expired') NOT NULL DEFAULT 'active',
    dns_hosted TINYINT(1) NOT NULL DEFAULT 0,
    dns_dirty TINYINT(1) NOT NULL DEFAULT 0,
    dns_synced_at DATETIME NULL,
    dns_published_at DATETIME NULL,
    registrar_status VARCHAR(40) NULL,
    expires_at DATE NULL,
    nameservers VARCHAR(500) NULL,
    assigned_at DATETIME NULL,
    suspend_reason VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_domains_customer (customer_id),
    KEY idx_domains_status (status),
    CONSTRAINT fk_domains_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_domains_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dns_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('A','AAAA','CNAME','ALIAS','MX','TXT','NS','SRV','CAA','SOA') NOT NULL,
    content TEXT NOT NULL,
    ttl INT UNSIGNED NOT NULL DEFAULT 14400,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_dns_domain (domain_id, name, type),
    CONSTRAINT fk_dns_domain FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE websites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(253) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NULL,
    domain_id INT UNSIGNED NULL,
    provider_id INT UNSIGNED NULL,
    provider_resource_id INT UNSIGNED NULL,
    external_username VARCHAR(64) NULL,
    external_order_id VARCHAR(64) NULL,
    root_directory VARCHAR(255) NULL,
    website_type VARCHAR(20) NULL,
    source ENUM('manual','discovered','created') NOT NULL DEFAULT 'manual',
    status ENUM('provisioning','active','suspended','disabled') NOT NULL DEFAULT 'active',
    suspend_reason VARCHAR(255) NULL,
    assigned_at DATETIME NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_websites_customer (customer_id),
    CONSTRAINT fk_websites_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_websites_domain FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE SET NULL,
    CONSTRAINT fk_websites_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hosting_databases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    db_user VARCHAR(64) NOT NULL,
    customer_id INT UNSIGNED NULL,
    website_id INT UNSIGNED NULL,
    provider_id INT UNSIGNED NULL,
    provider_resource_id INT UNSIGNED NULL,
    host VARCHAR(255) NULL,
    port INT UNSIGNED NOT NULL DEFAULT 3306,
    password_enc TEXT NULL,
    disk_usage_mb INT UNSIGNED NULL,
    max_size_mb INT UNSIGNED NULL,
    source ENUM('manual','discovered','created') NOT NULL DEFAULT 'manual',
    status ENUM('provisioning','active') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_db_name (provider_id, name),
    KEY idx_db_customer (customer_id),
    CONSTRAINT fk_db_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_db_website FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE SET NULL,
    CONSTRAINT fk_db_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ssl_checks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hostname VARCHAR(253) NOT NULL UNIQUE,
    status ENUM('active','expiring','expired','not_available') NOT NULL,
    issuer VARCHAR(255) NULL,
    subject VARCHAR(255) NULL,
    valid_from DATETIME NULL,
    valid_to DATETIME NULL,
    error VARCHAR(255) NULL,
    provider_status VARCHAR(30) NULL,
    checked_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

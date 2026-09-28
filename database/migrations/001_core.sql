-- WebEdge Solution: core schema (accounts, permissions, billing, logs)
-- Money is stored in paise (BIGINT). Percentages in basis points (1800 = 18%).

CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_super TINYINT(1) NOT NULL DEFAULT 0,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission VARCHAR(64) NOT NULL,
    PRIMARY KEY (role_id, permission),
    CONSTRAINT fk_role_perm_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    company VARCHAR(150) NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(30) NULL,
    gstin VARCHAR(15) NULL,
    address_line1 VARCHAR(255) NULL,
    address_line2 VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    state_code CHAR(2) NULL,
    country CHAR(2) NOT NULL DEFAULT 'IN',
    postal_code VARCHAR(20) NULL,
    status ENUM('active','suspended','closed') NOT NULL DEFAULT 'active',
    suspend_reason VARCHAR(255) NULL,
    suspended_at DATETIME NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_customers_email (email),
    KEY idx_customers_status (status),
    KEY idx_customers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('admin','customer') NOT NULL,
    customer_id INT UNSIGNED NULL,
    role_id INT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_owner TINYINT(1) NOT NULL DEFAULT 0,
    permissions TEXT NULL,
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    password_changed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_users_customer (customer_id),
    CONSTRAINT fk_users_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_login_email (email, created_at),
    KEY idx_login_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    `key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` TEXT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    price BIGINT NOT NULL DEFAULT 0,
    setup_fee BIGINT NOT NULL DEFAULT 0,
    billing_cycle ENUM('monthly','quarterly','semiannual','annual','biennial','triennial') NOT NULL DEFAULT 'annual',
    max_websites INT UNSIGNED NULL,
    max_domains INT UNSIGNED NULL,
    max_subdomains INT UNSIGNED NULL,
    storage_mb INT UNSIGNED NULL,
    bandwidth_gb INT UNSIGNED NULL,
    max_databases INT UNSIGNED NULL,
    max_mailboxes INT UNSIGNED NULL,
    mailbox_quota_mb INT UNSIGNED NULL,
    max_email_aliases INT UNSIGNED NULL,
    features TEXT NULL,
    status ENUM('active','withdrawn') NOT NULL DEFAULT 'active',
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    status ENUM('pending','active','suspended','cancelled','expired') NOT NULL DEFAULT 'active',
    billing_cycle ENUM('monthly','quarterly','semiannual','annual','biennial','triennial') NOT NULL,
    price BIGINT NOT NULL,
    start_date DATE NOT NULL,
    current_period_start DATE NOT NULL,
    current_period_end DATE NOT NULL,
    renewal_date DATE NOT NULL,
    auto_renew TINYINT(1) NOT NULL DEFAULT 1,
    cancelled_at DATETIME NULL,
    cancel_reason VARCHAR(255) NULL,
    suspended_at DATETIME NULL,
    suspend_reason VARCHAR(255) NULL,
    expiry_notified_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_subs_customer (customer_id),
    KEY idx_subs_renewal (status, renewal_date),
    CONSTRAINT fk_subs_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_subs_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT UNSIGNED NOT NULL,
    event VARCHAR(40) NOT NULL,
    from_plan_id INT UNSIGNED NULL,
    to_plan_id INT UNSIGNED NULL,
    description VARCHAR(500) NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    KEY idx_sub_events_sub (subscription_id),
    CONSTRAINT fk_sub_events_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE number_sequences (
    seq_key VARCHAR(50) NOT NULL PRIMARY KEY,
    last_number INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NULL,
    type ENUM('subscription','renewal','upgrade','manual') NOT NULL DEFAULT 'manual',
    status ENUM('pending','due','paid','failed','cancelled','refunded','void') NOT NULL DEFAULT 'pending',
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'INR',
    subtotal BIGINT NOT NULL DEFAULT 0,
    discount BIGINT NOT NULL DEFAULT 0,
    taxable_amount BIGINT NOT NULL DEFAULT 0,
    tax_type ENUM('intra','inter','none') NOT NULL DEFAULT 'none',
    cgst_rate SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    sgst_rate SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    igst_rate SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    cgst_amount BIGINT NOT NULL DEFAULT 0,
    sgst_amount BIGINT NOT NULL DEFAULT 0,
    igst_amount BIGINT NOT NULL DEFAULT 0,
    tax_amount BIGINT NOT NULL DEFAULT 0,
    total BIGINT NOT NULL DEFAULT 0,
    amount_paid BIGINT NOT NULL DEFAULT 0,
    amount_credited BIGINT NOT NULL DEFAULT 0,
    billing_name VARCHAR(150) NOT NULL,
    billing_company VARCHAR(150) NULL,
    billing_email VARCHAR(190) NULL,
    billing_gstin VARCHAR(15) NULL,
    billing_address TEXT NULL,
    billing_state_code CHAR(2) NULL,
    billing_country CHAR(2) NOT NULL DEFAULT 'IN',
    seller_gstin VARCHAR(15) NULL,
    seller_state_code CHAR(2) NULL,
    notes TEXT NULL,
    paid_at DATETIME NULL,
    voided_at DATETIME NULL,
    void_reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_invoices_customer (customer_id),
    KEY idx_invoices_status (status, due_date),
    KEY idx_invoices_date (invoice_date),
    CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_invoices_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    sac_code VARCHAR(10) NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price BIGINT NOT NULL,
    amount BIGINT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE credit_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    credit_note_number VARCHAR(30) NOT NULL UNIQUE,
    invoice_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    note_date DATE NOT NULL,
    reason VARCHAR(255) NOT NULL,
    taxable_amount BIGINT NOT NULL,
    cgst_amount BIGINT NOT NULL DEFAULT 0,
    sgst_amount BIGINT NOT NULL DEFAULT 0,
    igst_amount BIGINT NOT NULL DEFAULT 0,
    tax_amount BIGINT NOT NULL DEFAULT 0,
    total BIGINT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    KEY idx_cn_invoice (invoice_id),
    CONSTRAINT fk_cn_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cn_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    amount BIGINT NOT NULL,
    method VARCHAR(40) NOT NULL DEFAULT 'manual',
    gateway VARCHAR(40) NULL,
    gateway_order_id VARCHAR(100) NULL,
    gateway_payment_id VARCHAR(100) NULL,
    reference VARCHAR(100) NULL,
    status ENUM('pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    notes VARCHAR(500) NULL,
    paid_at DATETIME NULL,
    refunded_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_payments_invoice (invoice_id),
    KEY idx_payments_status (status, paid_at),
    UNIQUE KEY uq_payments_gateway (gateway, gateway_payment_id),
    CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE renewals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    invoice_id INT UNSIGNED NULL,
    status ENUM('invoiced','paid','void') NOT NULL DEFAULT 'invoiced',
    run_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_renewal_period (subscription_id, period_start),
    CONSTRAINT fk_renewals_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_renewals_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    type VARCHAR(40) NOT NULL,
    title VARCHAR(190) NOT NULL,
    message TEXT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    KEY idx_notif_customer (customer_id, is_read, created_at),
    CONSTRAINT fk_notif_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    audience VARCHAR(40) NOT NULL,
    recipients INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    user_type VARCHAR(10) NOT NULL,
    user_name VARCHAR(150) NOT NULL,
    customer_id INT UNSIGNED NULL,
    module VARCHAR(40) NOT NULL,
    action VARCHAR(60) NOT NULL,
    resource_type VARCHAR(40) NULL,
    resource_id VARCHAR(64) NULL,
    description VARCHAR(500) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_activity_created (created_at),
    KEY idx_activity_customer (customer_id, created_at),
    KEY idx_activity_user (user_id, created_at),
    KEY idx_activity_module (module, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE security_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    user_type VARCHAR(10) NULL,
    email VARCHAR(190) NULL,
    event VARCHAR(40) NOT NULL,
    status ENUM('success','failure','info') NOT NULL DEFAULT 'info',
    description VARCHAR(500) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_security_created (created_at),
    KEY idx_security_event (event, created_at),
    KEY idx_security_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, description, is_super, is_system, created_at, updated_at) VALUES
    ('Super Admin', 'Full access to everything, including settings and admin users.', 1, 1, NOW(), NOW()),
    ('Billing Manager', 'Plans, subscriptions, invoices and payments.', 0, 0, NOW(), NOW()),
    ('Support', 'Customer support: view customers and manage their hosting resources.', 0, 0, NOW(), NOW());

INSERT INTO role_permissions (role_id, permission)
SELECT id, p.permission FROM roles
JOIN (
    SELECT 'customers.view' AS permission UNION ALL SELECT 'plans.view' UNION ALL SELECT 'plans.manage'
    UNION ALL SELECT 'subscriptions.view' UNION ALL SELECT 'subscriptions.manage'
    UNION ALL SELECT 'invoices.view' UNION ALL SELECT 'invoices.manage'
    UNION ALL SELECT 'billing.view' UNION ALL SELECT 'billing.manage' UNION ALL SELECT 'activities.view'
) p
WHERE roles.name = 'Billing Manager';

INSERT INTO role_permissions (role_id, permission)
SELECT id, p.permission FROM roles
JOIN (
    SELECT 'customers.view' AS permission UNION ALL SELECT 'domains.view' UNION ALL SELECT 'domains.manage'
    UNION ALL SELECT 'dns.view' UNION ALL SELECT 'dns.manage' UNION ALL SELECT 'websites.view' UNION ALL SELECT 'websites.manage'
    UNION ALL SELECT 'files.manage' UNION ALL SELECT 'databases.view' UNION ALL SELECT 'email.view' UNION ALL SELECT 'email.manage'
    UNION ALL SELECT 'subscriptions.view' UNION ALL SELECT 'invoices.view' UNION ALL SELECT 'activities.view'
) p
WHERE roles.name = 'Support';

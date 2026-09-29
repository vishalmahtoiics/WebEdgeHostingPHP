-- Messages sent from the contact form on the public website.
CREATE TABLE IF NOT EXISTS enquiries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    interest VARCHAR(60) NULL,
    message TEXT NOT NULL,
    ip VARCHAR(45) NULL,
    status ENUM('new', 'read', 'closed') NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL,
    INDEX idx_enquiries_status (status, created_at),
    INDEX idx_enquiries_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

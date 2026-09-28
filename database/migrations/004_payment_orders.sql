-- Online payment orders (one per checkout attempt). A paid order links to the
-- payments row it produced; "review" means money was taken but could not be
-- applied automatically (e.g. the invoice was already settled).
CREATE TABLE payment_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    gateway VARCHAR(40) NOT NULL,
    gateway_order_id VARCHAR(100) NOT NULL,
    gateway_payment_id VARCHAR(100) NULL,
    amount BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'INR',
    status ENUM('created','paid','failed','review','resolved') NOT NULL DEFAULT 'created',
    payment_id INT UNSIGNED NULL,
    error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_payment_orders_gateway (gateway, gateway_order_id),
    KEY idx_payment_orders_invoice (invoice_id, status),
    KEY idx_payment_orders_status (status),
    CONSTRAINT fk_payment_orders_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
    CONSTRAINT fk_payment_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

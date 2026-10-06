CREATE DATABASE IF NOT EXISTS phone_support
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE phone_support;

DROP TABLE IF EXISTS support_routes;
DROP TABLE IF EXISTS voicemail_logs;
DROP TABLE IF EXISTS call_logs;
DROP TABLE IF EXISTS order_logs;
DROP TABLE IF EXISTS call_sessions;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS customers;

CREATE TABLE customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(180) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    order_number VARCHAR(40) NOT NULL UNIQUE,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('new', 'processing', 'shipped', 'delivered', 'on_hold', 'cancelled') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_orders_customer_created (customer_id, created_at),
    INDEX idx_orders_status (status)
) ENGINE=InnoDB;

CREATE TABLE call_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_sid VARCHAR(100) NOT NULL UNIQUE,
    caller_phone VARCHAR(30) NOT NULL,
    called_phone VARCHAR(30) NULL,
    customer_id BIGINT UNSIGNED NULL,
    selected_order_id BIGINT UNSIGNED NULL,
    state VARCHAR(40) NOT NULL DEFAULT 'main_menu',
    invalid_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_call_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_call_selected_order
        FOREIGN KEY (selected_order_id) REFERENCES orders(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_call_caller (caller_phone),
    INDEX idx_call_customer (customer_id),
    INDEX idx_call_started (started_at)
) ENGINE=InnoDB;

CREATE TABLE call_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_session_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(50) NOT NULL,
    message VARCHAR(500) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_call_logs_session
        FOREIGN KEY (call_session_id) REFERENCES call_sessions(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_call_logs_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_call_logs_session_created (call_session_id, created_at),
    INDEX idx_call_logs_event (event_type)
) ENGINE=InnoDB;

CREATE TABLE order_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    comment VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_logs_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_order_logs_order_created (order_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE voicemail_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_session_id BIGINT UNSIGNED NOT NULL,
    recording_url VARCHAR(500) NULL,
    recording_identifier VARCHAR(150) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_voicemail_session
        FOREIGN KEY (call_session_id) REFERENCES call_sessions(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_voicemail_call (call_session_id)
) ENGINE=InnoDB;

CREATE TABLE support_routes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_session_id BIGINT UNSIGNED NOT NULL,
    queue_name VARCHAR(100) NOT NULL,
    routing_decision VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_support_route_session
        FOREIGN KEY (call_session_id) REFERENCES call_sessions(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_support_route_call (call_session_id)
) ENGINE=InnoDB;

INSERT INTO customers (name, phone, email) VALUES
('Amit Sharma', '+919876543210', 'amit@example.com'),
('Priya Patil', '+919812345678', 'priya@example.com'),
('Rahul Kulkarni', '+919998887776', 'rahul@example.com');

INSERT INTO orders (customer_id, order_number, total_amount, status, created_at) VALUES
(1, 'ORD-1001', 1299.00, 'processing', '2026-09-30 10:00:00'),
(1, 'ORD-1002', 2499.50, 'shipped',    '2026-09-25 14:30:00'),
(1, 'ORD-1003', 799.00,   'delivered',  '2026-09-20 09:15:00'),
(2, 'ORD-2001', 1599.00, 'new',        '2026-09-29 11:20:00'),
(2, 'ORD-2002', 3299.00, 'processing', '2026-09-21 16:00:00'),
(3, 'ORD-3001', 899.00,  'delivered',  '2026-09-15 12:00:00');

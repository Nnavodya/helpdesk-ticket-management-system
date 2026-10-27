
CREATE DATABASE IF NOT EXISTS helpdesk_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE helpdesk_db;

-- 1. Users table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('employee', 'agent', 'admin')
        NOT NULL DEFAULT 'employee',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Ticket categories
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Tickets table
CREATE TABLE IF NOT EXISTS tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(20) NOT NULL UNIQUE,
    created_by INT UNSIGNED NOT NULL,
    assigned_to INT UNSIGNED NULL,
    category_id INT UNSIGNED NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,

    priority ENUM('low', 'medium', 'high', 'critical')
        NOT NULL DEFAULT 'medium',

    status ENUM(
        'open',
        'in_progress',
        'pending',
        'resolved',
        'closed'
    ) NOT NULL DEFAULT 'open',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,

    INDEX idx_ticket_status (status),
    INDEX idx_ticket_priority (priority),
    INDEX idx_ticket_creator (created_by),
    INDEX idx_ticket_agent (assigned_to),

    CONSTRAINT fk_ticket_creator
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_ticket_agent
        FOREIGN KEY (assigned_to)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_ticket_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON DELETE RESTRICT
);

-- 4. Ticket comments
CREATE TABLE IF NOT EXISTS ticket_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_comments_ticket (ticket_id),

    CONSTRAINT fk_comment_ticket
        FOREIGN KEY (ticket_id)
        REFERENCES tickets(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_comment_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
);

-- 5. Ticket activity history
CREATE TABLE IF NOT EXISTS ticket_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    changed_by INT UNSIGNED NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    note VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_history_ticket (ticket_id),

    CONSTRAINT fk_history_ticket
        FOREIGN KEY (ticket_id)
        REFERENCES tickets(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_history_user
        FOREIGN KEY (changed_by)
        REFERENCES users(id)
        ON DELETE RESTRICT
);

-- Default ticket categories
INSERT IGNORE INTO categories (name, description) VALUES
('Hardware', 'Computer, laptop and device issues'),
('Software', 'Application and software issues'),
('Network', 'Internet and network issues'),
('Account Access', 'Login and access issues'),
('Other', 'Other technical problems');

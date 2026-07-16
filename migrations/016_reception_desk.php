<?php
// migrations/016_reception_desk.php
return [
    "CREATE TABLE IF NOT EXISTS reception_visitors (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        visitor_name VARCHAR(255) NOT NULL,
        company VARCHAR(255),
        host_id INTEGER NOT NULL,
        status VARCHAR(50) DEFAULT 'expected',
        expected_arrival DATETIME,
        checked_in_at DATETIME NULL,
        checked_out_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS reception_packages (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        recipient_id INTEGER NOT NULL,
        courier VARCHAR(100),
        tracking_number VARCHAR(255),
        status VARCHAR(50) DEFAULT 'received',
        received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        picked_up_at DATETIME NULL,
        FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS reception_assets (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        asset_name VARCHAR(255) NOT NULL,
        asset_type VARCHAR(50) DEFAULT 'key',
        assigned_to INTEGER NOT NULL,
        status VARCHAR(50) DEFAULT 'checked_out',
        expected_return DATETIME NULL,
        checked_out_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        returned_at DATETIME NULL,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE
    )"
];

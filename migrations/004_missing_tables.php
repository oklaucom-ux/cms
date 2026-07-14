<?php
global $pdo;

$queries = [
    "CREATE TABLE IF NOT EXISTS api_keys (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id VARCHAR(255) NOT NULL,
        api_key VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS crm_leads (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        lead_name VARCHAR(255) NOT NULL,
        company VARCHAR(255),
        email VARCHAR(255),
        phone VARCHAR(255),
        value DECIMAL(10,2) DEFAULT 0,
        stage VARCHAR(255) DEFAULT 'Prospect',
        owner_id VARCHAR(255),
        assigned_to VARCHAR(255),
        source VARCHAR(255),
        custom_data TEXT,
        branch_id VARCHAR(255) DEFAULT 'Global HQ',
        follow_up_date DATE,
        last_contact DATETIME DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(255) DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS chat_channels (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS channel_read_state (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id VARCHAR(255) NOT NULL,
        channel_name VARCHAR(255) NOT NULL,
        last_read_msg_id INTEGER DEFAULT 0
    )",
    "ALTER TABLE purchase_orders ADD COLUMN vendor_name TEXT",
    "ALTER TABLE purchase_orders ADD COLUMN department TEXT",
    "ALTER TABLE purchase_orders ADD COLUMN created_by TEXT"
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
    } catch (Exception $e) {
        // silently ignore duplicate columns
    }
}

return [];

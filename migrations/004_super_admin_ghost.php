<?php
return [
    "CREATE TABLE IF NOT EXISTS super_admins (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        login_id VARCHAR(255) UNIQUE NOT NULL,
        password TEXT NOT NULL,
        name TEXT NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        role VARCHAR(255) DEFAULT 'Super Admin',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "INSERT INTO super_admins (login_id, password, name, email, role) SELECT login_id, password, name, email, role FROM users WHERE role = 'Super Admin'",
    "DELETE FROM users WHERE role = 'Super Admin'"
];

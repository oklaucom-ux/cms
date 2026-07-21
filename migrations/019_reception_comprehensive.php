<?php
// migrations/019_reception_comprehensive.php

$queries = [
    // Visitors additions
    "ALTER TABLE reception_visitors ADD COLUMN purpose VARCHAR(255) NULL",
    "ALTER TABLE reception_visitors ADD COLUMN vehicle_reg VARCHAR(100) NULL",
    "ALTER TABLE reception_visitors ADD COLUMN is_nda_signed BOOLEAN DEFAULT 0",
    "ALTER TABLE reception_visitors ADD COLUMN photo_url VARCHAR(255) NULL",
    
    // Packages additions
    "ALTER TABLE reception_packages ADD COLUMN sender_name VARCHAR(255) NULL",
    "ALTER TABLE reception_packages ADD COLUMN sender_company VARCHAR(255) NULL",
    "ALTER TABLE reception_packages ADD COLUMN package_type VARCHAR(100) DEFAULT 'Box'",
    
    // Assets additions
    "ALTER TABLE reception_assets ADD COLUMN condition_out TEXT NULL",
    "ALTER TABLE reception_assets ADD COLUMN condition_in TEXT NULL",
    "ALTER TABLE reception_assets ADD COLUMN notes TEXT NULL"
];

return $queries;

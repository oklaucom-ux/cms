<?php
require 'includes/db.php';
try {
    $pdo->exec("INSERT INTO office_folders (name, parent_id, created_by) VALUES ('test_folder', 0, 'admin')");
    echo "Folder created successfully.\n";
} catch (Exception $e) {
    echo "Folder creation failed: " . $e->getMessage() . "\n";
}
try {
    $pdo->exec("INSERT INTO office_files (file_type, file_name, json_data, created_by, visibility, shared_with, folder_id, locked_by, approval_status) VALUES ('Word', 'test.docx', '{}', 'admin', 'Private', '[]', 0, 'admin', 'Draft')");
    echo "File created successfully.\n";
} catch (Exception $e) {
    echo "File creation failed: " . $e->getMessage() . "\n";
}
?>

<?php
session_start();
require_once '../includes/db.php';

requirePermission($pdo, 'upload_documents');

// Migrate Versioning Columns
try { $pdo->exec("ALTER TABLE documents ADD COLUMN version INTEGER DEFAULT 1"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE documents ADD COLUMN parent_doc_id INTEGER DEFAULT NULL"); } catch(Exception $e){}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['document'])) {
    $title = $_POST['title'];
    $category = $_POST['category'];
    $visible_role = $_POST['visible_to_role'] ?? 'ALL';
    
    // Setup physical upload path
    $upload_dir = '../uploads/documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Auto-generate .htaccess to block script execution in the upload folder
    $htaccess_path = $upload_dir . '.htaccess';
    if (!file_exists($htaccess_path)) {
        $htaccess_content = "php_flag engine off\n<FilesMatch \"\.(php|php3|php4|php5|phtml|pl|py|cgi|exe|sh|bat)$\">\nOrder Deny,Allow\nDeny from all\n</FilesMatch>\nOptions -ExecCGI";
        file_put_contents($htaccess_path, $htaccess_content);
    }
    
    $file_info = pathinfo($_FILES['document']['name']);
    $ext = strtolower($file_info['extension']);
    
    // Prevent malicious PHP uploads
    if (in_array($ext, ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'sh'])) {
        die("Invalid file type.");
    }
    
    $safe_filename = uniqid('doc_') . '.' . $ext;
    $target_path = $upload_dir . $safe_filename;
    
    if (move_uploaded_file($_FILES['document']['tmp_name'], $target_path)) {
        $db_path = 'uploads/documents/' . $safe_filename;
        
        // Delta Versioning Logic
        $vStmt = $pdo->prepare("SELECT id, version, parent_doc_id FROM documents WHERE title = ? ORDER BY version DESC LIMIT 1");
        $vStmt->execute([$title]);
        $existing = $vStmt->fetch(PDO::FETCH_ASSOC);
        
        $new_version = 1;
        $parent_id = null;
        
        if ($existing) {
            $new_version = $existing['version'] + 1;
            $parent_id = $existing['parent_doc_id'] ?: $existing['id']; // Inherit top-level parent
        }
        
        $stmt = $pdo->prepare("INSERT INTO documents (title, file_path, category, uploaded_by, visible_to_role, version, parent_doc_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $db_path, $category, $_SESSION['login_id'], $visible_role, $new_version, $parent_id]);
        
        $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'Upload Document', 'Uploaded {$title} v{$new_version}')");
    }
    header("Location: ../documents.php");
}
?>

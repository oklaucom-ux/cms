<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/flash.php';
requirePermission($pdo, 'edit_projects');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file']) && isset($_POST['project_id'])) {
    $project_id = intval($_POST['project_id']);
    
    $file = $_FILES['file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $name = basename($file['name']);
        
        // Strip out dangerous extensions
        if (preg_match('/\.(php|phtml|exe|sh|pl|py|cgi)$/i', $name)) {
            setFlash('error', 'Disallowed file type.');
            header("Location: ../projects.php");
            exit();
        }

        $clean_name  = preg_replace("/[^a-zA-Z0-9.\-_]/", "", $name);
        $unique_name = uniqid() . '_' . $clean_name;
        $upload_base = realpath(__DIR__ . '/../uploads/projects');
        if (!$upload_base) {
            mkdir(__DIR__ . '/../uploads/projects', 0755, true);
            $upload_base = realpath(__DIR__ . '/../uploads/projects');
        }
        $dest = $upload_base . DIRECTORY_SEPARATOR . $unique_name;
        
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $stmt = $pdo->prepare("INSERT INTO project_files (project_id, uploader_id, file_name, file_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$project_id, $_SESSION['login_id'], $name, "uploads/projects/" . $unique_name]);
            setFlash('success', 'File uploaded successfully.');
        } else {
            setFlash('error', 'File move failed.');
        }
    } else {
        setFlash('error', 'Upload error code: ' . $file['error']);
    }
} else {
    setFlash('error', 'Invalid upload request.');
}

header("Location: ../projects.php");
exit();
?>

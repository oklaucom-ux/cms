<?php
/**
 * upload_validator.php — Centralized File Upload Security
 * 
 * Usage:
 *   require_once 'includes/upload_validator.php';
 *   $result = validateUpload($_FILES['file'], 'documents');
 *   if ($result['error']) die($result['error']);
 *   // Use $result['safe_name'] and $result['ext'] for storage
 */

// Maximum file sizes per category (in bytes)
define('UPLOAD_LIMITS', [
    'documents'  => 10 * 1024 * 1024,   // 10 MB
    'images'     => 5 * 1024 * 1024,    // 5 MB
    'hr_files'   => 10 * 1024 * 1024,   // 10 MB
    'avatars'    => 2 * 1024 * 1024,    // 2 MB
    'projects'   => 25 * 1024 * 1024,   // 25 MB
    'default'    => 10 * 1024 * 1024,   // 10 MB
]);

// Allowed extensions per category (allowlist approach)
define('ALLOWED_EXTENSIONS', [
    'documents' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'odt', 'ods'],
    'images'    => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
    'hr_files'  => ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'],
    'avatars'   => ['jpg', 'jpeg', 'png', 'webp'],
    'projects'  => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'png', 'jpg', 'jpeg'],
    'default'   => ['pdf', 'doc', 'docx', 'txt', 'csv', 'png', 'jpg', 'jpeg'],
]);

// Dangerous extensions that are ALWAYS blocked regardless of category
define('BLOCKED_EXTENSIONS', [
    'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
    'exe', 'bat', 'cmd', 'sh', 'bash', 'cgi', 'pl', 'py', 'rb', 'jsp',
    'asp', 'aspx', 'htaccess', 'htpasswd', 'ini', 'env', 'sql',
]);

// MIME types that indicate executable content (double-check even if extension passes)
define('BLOCKED_MIMES', [
    'application/x-httpd-php',
    'application/x-php',
    'text/x-php',
    'application/x-executable',
    'application/x-sharedlib',
    'application/x-msdos-program',
]);

/**
 * Validate an uploaded file for security and size constraints.
 * 
 * @param array  $file     The $_FILES['field'] array
 * @param string $category Upload category (documents, images, hr_files, avatars, projects)
 * @return array ['error' => string|null, 'ext' => string, 'safe_name' => string, 'size' => int]
 */
function validateUpload(array $file, string $category = 'default'): array {
    $result = ['error' => null, 'ext' => '', 'safe_name' => '', 'size' => 0];
    
    // 1. Check for PHP upload errors
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server maximum upload size.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form maximum size.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
        ];
        $result['error'] = $errors[$file['error']] ?? 'Unknown upload error.';
        return $result;
    }
    
    // 2. Verify it's actually an uploaded file (prevents path traversal)
    if (!is_uploaded_file($file['tmp_name'])) {
        $result['error'] = 'Invalid upload mechanism.';
        return $result;
    }
    
    // 3. Extract and validate extension
    $originalName = basename($file['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $result['ext'] = $ext;
    
    // Block dangerous extensions
    if (in_array($ext, BLOCKED_EXTENSIONS)) {
        $result['error'] = "File type .{$ext} is not allowed.";
        return $result;
    }
    
    // Check allowlist for this category
    $allowed = ALLOWED_EXTENSIONS[$category] ?? ALLOWED_EXTENSIONS['default'];
    if (!in_array($ext, $allowed)) {
        $result['error'] = "File type .{$ext} is not allowed for {$category} uploads. Allowed: " . implode(', ', $allowed);
        return $result;
    }
    
    // 4. Check MIME type (defense in depth — prevents .php renamed to .jpg)
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (in_array($mime, BLOCKED_MIMES)) {
            $result['error'] = 'File content appears to be executable code.';
            return $result;
        }
    }
    
    // 5. Check file size
    $maxSize = UPLOAD_LIMITS[$category] ?? UPLOAD_LIMITS['default'];
    $result['size'] = $file['size'];
    if ($file['size'] > $maxSize) {
        $maxMB = round($maxSize / 1024 / 1024, 1);
        $result['error'] = "File is too large. Maximum size for {$category}: {$maxMB} MB.";
        return $result;
    }
    
    // 6. Check for null bytes in filename (path traversal attack vector)
    if (strpos($originalName, "\0") !== false) {
        $result['error'] = 'Invalid filename.';
        return $result;
    }
    
    // 7. Generate a safe filename
    $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '', pathinfo($originalName, PATHINFO_FILENAME));
    $safeBase = substr($safeBase, 0, 50); // Truncate long names
    $result['safe_name'] = $safeBase . '_' . uniqid() . '.' . $ext;
    
    return $result;
}

/**
 * Ensure upload directory exists and has an .htaccess blocking script execution.
 */
function secureUploadDir(string $dirPath): void {
    if (!is_dir($dirPath)) {
        mkdir($dirPath, 0755, true);
    }
    
    $htaccess = $dirPath . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, 
            "php_flag engine off\n" .
            "<FilesMatch \"\\.(php|php3|php4|php5|phtml|pl|py|cgi|exe|sh|bat)$\">\n" .
            "Order Deny,Allow\nDeny from all\n" .
            "</FilesMatch>\n" .
            "Options -ExecCGI\n"
        );
    }
}

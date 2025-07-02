<?php
// =====================================================
// document_download.php - Secure Document Download
// =====================================================

// Include functions first
require_once 'includes/functions.php';

// Initialize error tracking
$error_message = '';
$debug_info = [];

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        $error_message = 'Unauthorized access. Please login first.';
        redirect('login.php?error=' . urlencode($error_message));
        exit();
    }

    // Check basic permission (viewer level required for document download)
    if (!hasPermission('viewer')) {
        $error_message = 'Insufficient permissions for document download.';
        redirect('dashboard.php?error=' . urlencode($error_message));
        exit();
    }

    // Get and validate document ID
    $document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $force_download = isset($_GET['download']) ? (bool)$_GET['download'] : false;
    $preview_mode = isset($_GET['preview']) ? (bool)$_GET['preview'] : false;

    if ($document_id <= 0) {
        $error_message = 'Invalid document ID provided.';
        redirect('jobs.php?error=' . urlencode($error_message));
        exit();
    }

    // Get document information with job and permission details
    $document = fetchOne("
        SELECT d.*, 
               j.id as job_id, j.job_no, j.status as job_status,
               j.shipper_id, j.consignee_id,
               c1.company_name as shipper_name,
               c2.company_name as consignee_name,
               u.name as uploaded_by_name, u.id as uploaded_by_id
        FROM documents d
        INNER JOIN jobs j ON d.job_id = j.id
        LEFT JOIN customers c1 ON j.shipper_id = c1.id
        LEFT JOIN customers c2 ON j.consignee_id = c2.id
        LEFT JOIN users u ON d.uploaded_by = u.id
        WHERE d.id = ?
    ", [$document_id]);

    if (!$document) {
        $error_message = 'Document not found in database.';
        redirect('jobs.php?error=' . urlencode($error_message));
        exit();
    }

    $debug_info['document_found'] = true;
    $debug_info['job_no'] = $document['job_no'];

    // Permission-based access control
    $can_download = false;
    $access_reason = '';

    // Check user access permissions
    if (hasPermission('admin')) {
        $can_download = true;
        $access_reason = 'Admin access';
    } elseif (hasPermission('manager')) {
        $can_download = true;
        $access_reason = 'Manager access';
    } elseif (hasPermission('staff')) {
        $can_download = true;
        $access_reason = 'Staff access';
    } elseif (hasPermission('viewer')) {
        // Viewers can only download if they have connection to the job
        // (uploaded the document, or involved in the job as customer contact)
        if ($document['uploaded_by_id'] == $_SESSION['user_id']) {
            $can_download = true;
            $access_reason = 'Document uploader';
        } else {
            // Additional business logic for customer-specific access could be added here
            $can_download = true; // For now, allow all viewers
            $access_reason = 'Viewer access';
        }
    }

    if (!$can_download) {
        $error_message = 'You do not have permission to download this document.';
        redirect('jobs_view.php?id=' . $document['job_id'] . '&error=' . urlencode($error_message));
        exit();
    }

    $debug_info['access_granted'] = true;
    $debug_info['access_reason'] = $access_reason;

    // Build full file path
    $file_path = $document['file_path'];
    
    // Handle both absolute and relative paths
    if (!file_exists($file_path)) {
        // Try with different base paths
        $possible_paths = [
            $file_path,
            __DIR__ . '/' . $file_path,
            __DIR__ . '/../' . $file_path,
            'uploads/documents/' . basename($file_path)
        ];
        
        $found_path = null;
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $found_path = $path;
                break;
            }
        }
        
        if (!$found_path) {
            $error_message = 'Document file not found on server. File may have been moved or deleted.';
            $debug_info['file_paths_tried'] = $possible_paths;
            redirect('jobs_view.php?id=' . $document['job_id'] . '&error=' . urlencode($error_message));
            exit();
        }
        
        $file_path = $found_path;
    }

    $debug_info['file_found'] = true;
    $debug_info['file_path'] = $file_path;

    // Security: Ensure file is within allowed directories
    $real_file_path = realpath($file_path);
    $allowed_base_paths = [
        realpath(__DIR__ . '/uploads/'),
        realpath(__DIR__ . '/../uploads/'),
    ];
    
    $path_allowed = false;
    foreach ($allowed_base_paths as $base_path) {
        if ($base_path && strpos($real_file_path, $base_path) === 0) {
            $path_allowed = true;
            break;
        }
    }
    
    if (!$path_allowed) {
        $error_message = 'Security violation: File path not allowed.';
        error_log("Security Alert - Unauthorized file access attempt: {$real_file_path} by user {$_SESSION['username']}");
        redirect('jobs_view.php?id=' . $document['job_id'] . '&error=' . urlencode($error_message));
        exit();
    }

    // Get file information
    $file_size = filesize($real_file_path);
    $file_extension = strtolower(pathinfo($document['file_name'], PATHINFO_EXTENSION));
    $mime_type = getMimeType($file_extension, $real_file_path);
    
    $debug_info['file_size'] = $file_size;
    $debug_info['mime_type'] = $mime_type;

    // Determine download filename (use original document name)
    $download_filename = $document['document_name'];
    if (!pathinfo($download_filename, PATHINFO_EXTENSION)) {
        $download_filename .= '.' . $file_extension;
    }
    
    // Sanitize filename for download
    $download_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $download_filename);
    
    // Log download activity
    logDownloadActivity($document_id, $_SESSION['user_id'], $access_reason);

    // Set appropriate headers for download/preview
    if ($preview_mode && in_array($file_extension, ['pdf', 'jpg', 'jpeg', 'png', 'gif'])) {
        // Preview mode - display in browser
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: inline; filename="' . $download_filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
    } else {
        // Download mode - force download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $download_filename . '"');
        header('Content-Transfer-Encoding: binary');
    }
    
    // Common headers
    header('Content-Length: ' . $file_size);
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Security headers
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Download-Options: noopen');

    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Handle large file downloads with chunked reading
    $chunk_size = 8192; // 8KB chunks
    $handle = fopen($real_file_path, 'rb');
    
    if ($handle === false) {
        throw new Exception('Unable to open file for reading.');
    }

    // Output file in chunks
    while (!feof($handle)) {
        $chunk = fread($handle, $chunk_size);
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        flush();
    }
    
    fclose($handle);
    exit();

} catch (Exception $e) {
    // Log error
    error_log("Document Download Error: " . $e->getMessage() . " - Document ID: " . ($document_id ?? 'Unknown') . " - User: " . ($_SESSION['username'] ?? 'Unknown'));
    
    // Clean any output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $error_message = 'Error downloading document: ' . $e->getMessage();
    
    // Redirect to appropriate page based on available information
    if (isset($document['job_id'])) {
        redirect('jobs_view.php?id=' . $document['job_id'] . '&error=' . urlencode($error_message));
    } else {
        redirect('jobs.php?error=' . urlencode($error_message));
    }
}

// =====================================================
// Helper Functions
// =====================================================

/**
 * Get MIME type for file
 * @param string $extension
 * @param string $file_path
 * @return string
 */
function getMimeType($extension, $file_path) {
    // Primary: Use PHP's built-in function
    if (function_exists('mime_content_type') && file_exists($file_path)) {
        $mime = mime_content_type($file_path);
        if ($mime !== false) {
            return $mime;
        }
    }
    
    // Fallback: Extension-based mapping
    $mime_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        'tar' => 'application/x-tar',
        'gz' => 'application/gzip'
    ];
    
    return isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';
}

/**
 * Log download activity for audit trail
 * @param int $document_id
 * @param int $user_id
 * @param string $access_reason
 */
function logDownloadActivity($document_id, $user_id, $access_reason) {
    try {
        execute("
            INSERT INTO document_download_log 
            (document_id, user_id, download_time, ip_address, user_agent, access_reason)
            VALUES (?, ?, NOW(), ?, ?, ?)
        ", [
            $document_id,
            $user_id,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            $access_reason
        ]);
    } catch (Exception $e) {
        // Don't fail download if logging fails, just log the error
        error_log("Failed to log download activity: " . $e->getMessage());
    }
}

/**
 * Create download log table if needed (run once)
 */
function createDownloadLogTable() {
    $sql = "
    CREATE TABLE IF NOT EXISTS document_download_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        user_id INT NOT NULL,
        download_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        user_agent TEXT,
        access_reason VARCHAR(100),
        INDEX idx_document_id (document_id),
        INDEX idx_user_id (user_id),
        INDEX idx_download_time (download_time),
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    execute($sql);
}

// Uncomment to create the download log table
// createDownloadLogTable();

/**
 * Format file size for human reading
 * @param int $bytes
 * @return string
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.1f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
}

/**
 * Check if file type supports preview
 * @param string $extension
 * @return bool
 */
function supportsPreview($extension) {
    $previewable_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'txt'];
    return in_array(strtolower($extension), $previewable_extensions);
}

/**
 * Validate file integrity (basic check)
 * @param string $file_path
 * @param int $expected_size
 * @return bool
 */
function validateFileIntegrity($file_path, $expected_size) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    $actual_size = filesize($file_path);
    return $actual_size === $expected_size;
}

/**
 * Generate secure download token (for future use)
 * @param int $document_id
 * @param int $user_id
 * @param int $expiry_hours
 * @return string
 */
function generateDownloadToken($document_id, $user_id, $expiry_hours = 24) {
    $expiry = time() + ($expiry_hours * 3600);
    $data = json_encode([
        'doc_id' => $document_id,
        'user_id' => $user_id,
        'expires' => $expiry
    ]);
    
    // In production, use proper encryption
    return base64_encode($data);
}

/**
 * Validate download token (for future use)
 * @param string $token
 * @return array|false
 */
function validateDownloadToken($token) {
    try {
        $data = json_decode(base64_decode($token), true);
        
        if (!$data || !isset($data['expires']) || $data['expires'] < time()) {
            return false;
        }
        
        return $data;
    } catch (Exception $e) {
        return false;
    }
}
?>
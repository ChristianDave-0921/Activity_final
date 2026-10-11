<?php
// download_file.php - Secure File Viewer & Downloader
require_once 'auth_check.php';
require_once 'file_helper.php';

$file = trim($_GET['file'] ?? '');
$action = trim($_GET['action'] ?? 'view'); // 'view' (inline) or 'download' (attachment)

if (empty($file)) {
    die("Error: No file specified.");
}

// Clean file to prevent directory traversal
$cleanFileName = basename($file);
$filePath = UPLOAD_DIR . $cleanFileName;

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    die("Error 404: The requested record file was not found on this server.");
}

$ext = strtolower(pathinfo($cleanFileName, PATHINFO_EXTENSION));

// Determine content type
$contentTypes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'csv'  => 'text/csv',
    'zip'  => 'application/zip',
    'txt'  => 'text/plain',
];

$contentType = $contentTypes[$ext] ?? 'application/octet-stream';
$disposition = ($action === 'download' || !in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt'])) 
               ? 'attachment' 
               : 'inline';

// Clear output buffers
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $contentType);
header('Content-Disposition: ' . $disposition . '; filename="' . $cleanFileName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit();

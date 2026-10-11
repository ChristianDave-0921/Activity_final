<?php
// file_helper.php - Separate Handlers for Profile Pictures and Record Files Data

define('UPLOAD_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);

// 1. Profile Picture Constraints (Images Only)
const PROFILE_PIC_MAX_SIZE = 3 * 1024 * 1024; // 3 MB
const PROFILE_PIC_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
const PROFILE_PIC_MIMES = [
    'image/jpeg',
    'image/pjpeg',
    'image/png',
    'image/webp',
    'image/gif'
];

// 2. Record File Constraints (Files Data Only: Documents, Spreadsheets, Archives, Text)
const RECORD_FILE_MAX_SIZE = 5 * 1024 * 1024; // 5 MB
const RECORD_FILE_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'];
const RECORD_FILE_MIMES = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
    'text/csv',
    'application/zip',
    'application/x-zip-compressed'
];

/**
 * Handle student profile picture upload (Images Only)
 *
 * @param array $file $_FILES['profile_picture']
 * @param string $studentId
 * @param string|null &$error
 * @return string|null|false Filename on success, null if no file, false on error
 */
function handle_profile_picture_upload(array $file, string $studentId, ?string &$error = null): string|null|false
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Profile picture upload error (Code: " . $file['error'] . ").";
        return false;
    }

    if ($file['size'] > PROFILE_PIC_MAX_SIZE) {
        $error = "Profile picture exceeds the maximum allowed size of 3MB.";
        return false;
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, PROFILE_PIC_EXTENSIONS, true)) {
        $error = "Invalid picture format (." . htmlspecialchars($extension) . "). Please upload JPG, PNG, WEBP, or GIF.";
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, PROFILE_PIC_MIMES, true)) {
        $error = "Uploaded file is not a valid image (" . htmlspecialchars($mimeType) . ").";
        return false;
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $cleanId = preg_replace('/[^a-zA-Z0-9_-]/', '', $studentId);
    $fileName = "profile_" . ($cleanId ?: 'std') . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $extension;
    $targetPath = UPLOAD_DIR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $error = "Failed to save profile picture to server directory.";
        return false;
    }

    return $fileName;
}

/**
 * Handle student record file upload (Files Data Only)
 *
 * @param array $file $_FILES['record_file']
 * @param string $studentId
 * @param string|null &$error
 * @return string|null|false Filename on success, null if no file, false on error
 */
function handle_record_file_upload(array $file, string $studentId, ?string &$error = null): string|null|false
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Record file upload error (Code: " . $file['error'] . ").";
        return false;
    }

    if ($file['size'] > RECORD_FILE_MAX_SIZE) {
        $error = "Record file exceeds the maximum allowed size of 5MB.";
        return false;
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, RECORD_FILE_EXTENSIONS, true)) {
        $error = "Invalid file type (." . htmlspecialchars($extension) . "). Allowed data files: " . implode(', ', RECORD_FILE_EXTENSIONS);
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $isAllowedMime = in_array($mimeType, RECORD_FILE_MIMES, true);
    // Allow office zip containers
    if (!$isAllowedMime && in_array($extension, ['docx', 'xlsx'], true)) {
        $isAllowedMime = in_array($mimeType, ['application/zip', 'application/octet-stream', 'application/x-zip'], true);
    }

    if (!$isAllowedMime) {
        $error = "Invalid file format (" . htmlspecialchars($mimeType) . "). Please upload document/data files.";
        return false;
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $cleanId = preg_replace('/[^a-zA-Z0-9_-]/', '', $studentId);
    $fileName = "file_" . ($cleanId ?: 'std') . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $extension;
    $targetPath = UPLOAD_DIR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $error = "Failed to save record file to server directory.";
        return false;
    }

    return $fileName;
}

/**
 * Safely delete an uploaded file from disk
 *
 * @param string|null $fileName
 * @return bool
 */
function delete_record_file(?string $fileName): bool
{
    if (empty($fileName)) {
        return false;
    }

    $cleanFileName = basename($fileName);
    $filePath = UPLOAD_DIR . $cleanFileName;

    if (file_exists($filePath) && is_file($filePath)) {
        return @unlink($filePath);
    }

    return false;
}

/**
 * Check if a file exists on disk
 *
 * @param string|null $fileName
 * @return bool
 */
function record_file_exists(?string $fileName): bool
{
    if (empty($fileName)) {
        return false;
    }
    return file_exists(UPLOAD_DIR . basename($fileName));
}

/**
 * Get human-readable file size from disk
 *
 * @param string|null $fileName
 * @return string
 */
function get_record_file_size(?string $fileName): string
{
    if (empty($fileName)) {
        return '';
    }
    $clean = basename($fileName);
    $path = UPLOAD_DIR . $clean;
    if (file_exists($path)) {
        $bytes = filesize($path);
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
    return '';
}

/**
 * Get styled badge HTML for record file data
 *
 * @param string|null $fileName
 * @return string
 */
function get_file_type_badge(?string $fileName): string
{
    if (empty($fileName)) {
        return '<span class="text-muted small fst-italic">No file</span>';
    }
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    switch ($ext) {
        case 'pdf':
            return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-file-earmark-pdf-fill me-1"></i>PDF</span>';
        case 'doc':
        case 'docx':
            return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-file-earmark-word-fill me-1"></i>DOC</span>';
        case 'xls':
        case 'xlsx':
        case 'csv':
            return '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-file-earmark-excel-fill me-1"></i>EXCEL</span>';
        case 'zip':
            return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="bi bi-file-earmark-zip-fill me-1"></i>ZIP</span>';
        case 'txt':
            return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="bi bi-file-earmark-text-fill me-1"></i>TXT</span>';
        default:
            return '<span class="badge bg-light text-dark border"><i class="bi bi-file-earmark-fill me-1"></i>FILE</span>';
    }
}

/**
 * Handle student or admin document/file request attachment upload
 * Supports documents and images up to 5MB
 *
 * @param array $file $_FILES item
 * @param string $prefix Prefix for generated file name
 * @param string|null &$error
 * @return string|null|false Filename on success, null if no file, false on error
 */
function handle_request_attachment_upload(array $file, string $prefix = 'req', ?string &$error = null): string|null|false
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload error (Code: " . $file['error'] . ").";
        return false;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        $error = "File exceeds the maximum allowed size of 5MB.";
        return false;
    }

    $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'jpg', 'jpeg', 'png', 'webp'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExts, true)) {
        $error = "Invalid file type (." . htmlspecialchars($extension) . "). Allowed: " . implode(', ', $allowedExts);
        return false;
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $cleanPrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix);
    $fileName = ($cleanPrefix ?: 'doc') . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $extension;
    $targetPath = UPLOAD_DIR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $error = "Failed to save file to server directory.";
        return false;
    }

    return $fileName;
}

/**
 * Get styled status badge HTML for File Request
 *
 * @param string $status
 * @return string
 */
function get_request_status_badge(string $status): string
{
    $st = ucfirst(strtolower(trim($status)));
    switch ($st) {
        case 'Approved':
            return '<span class="badge bg-success px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i>Approved</span>';
        case 'Rejected':
            return '<span class="badge bg-danger px-2 py-1"><i class="bi bi-x-circle-fill me-1"></i>Rejected</span>';
        case 'Pending':
        default:
            return '<span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-hourglass-split me-1"></i>Pending</span>';
    }
}


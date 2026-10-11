<?php
// process_file_request.php - Administrator Handler for Student File Requests (Approve, Reject, Delete)
require_once 'auth_check.php';
require_admin(); // Restrict to Administrators Only
require_once 'db_connection.php';
require_once 'file_helper.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action    = trim($_POST['action'] ?? '');
    $requestId = intval($_POST['request_id'] ?? 0);

    if ($requestId <= 0) {
        $_SESSION['error'] = "Invalid file request ID.";
        header("Location: dashboard.php#fileRequestsSection");
        exit();
    }

    // Fetch existing request
    $stmt = $conn->prepare("SELECT * FROM file_requests WHERE id = ?");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $currReq = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$currReq) {
        $_SESSION['error'] = "File request record not found.";
        header("Location: dashboard.php#fileRequestsSection");
        exit();
    }

    if ($action === 'approve_request') {
        $adminNotes = trim($_POST['admin_notes'] ?? '');
        $approvedFile = $currReq['approved_file'];

        // Handle uploaded approved document (if provided)
        if (isset($_FILES['approved_file']) && $_FILES['approved_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadErr = null;
            $newFile = handle_request_attachment_upload($_FILES['approved_file'], 'approved_' . $requestId, $uploadErr);
            if ($newFile !== false) {
                // Delete previous file if replacing
                if (!empty($currReq['approved_file'])) {
                    delete_record_file($currReq['approved_file']);
                }
                $approvedFile = $newFile;
            } else {
                $_SESSION['error'] = "File upload failed: " . ($uploadErr ?: "Invalid document format.");
                header("Location: dashboard.php#fileRequestsSection");
                exit();
            }
        }

        $notesToSave = !empty($adminNotes) ? $adminNotes : ($currReq['admin_notes'] ?: 'Your request has been approved by the registrar.');

        $upStmt = $conn->prepare("UPDATE file_requests SET status = 'Approved', admin_notes = ?, approved_file = ? WHERE id = ?");
        $upStmt->bind_param("ssi", $notesToSave, $approvedFile, $requestId);
        if ($upStmt->execute()) {
            $_SESSION['success'] = "Request #$requestId for '" . htmlspecialchars($currReq['document_type']) . "' has been marked as Approved.";
        } else {
            $_SESSION['error'] = "Failed to update request: " . $conn->error;
        }
        $upStmt->close();

    } elseif ($action === 'reject_request') {
        $adminNotes = trim($_POST['admin_notes'] ?? 'Your file request could not be processed at this time.');

        $upStmt = $conn->prepare("UPDATE file_requests SET status = 'Rejected', admin_notes = ? WHERE id = ?");
        $upStmt->bind_param("si", $adminNotes, $requestId);
        if ($upStmt->execute()) {
            $_SESSION['success'] = "Request #$requestId for '" . htmlspecialchars($currReq['document_type']) . "' has been marked as Rejected.";
        } else {
            $_SESSION['error'] = "Failed to update request: " . $conn->error;
        }
        $upStmt->close();

    } elseif ($action === 'delete_request') {
        // Delete any files on disk
        if (!empty($currReq['supporting_file'])) {
            delete_record_file($currReq['supporting_file']);
        }
        if (!empty($currReq['approved_file'])) {
            delete_record_file($currReq['approved_file']);
        }

        $delStmt = $conn->prepare("DELETE FROM file_requests WHERE id = ?");
        $delStmt->bind_param("i", $requestId);
        if ($delStmt->execute()) {
            $_SESSION['success'] = "File request #$requestId deleted successfully.";
        } else {
            $_SESSION['error'] = "Failed to delete request: " . $conn->error;
        }
        $delStmt->close();
    }
}

header("Location: dashboard.php#fileRequestsSection");
exit();

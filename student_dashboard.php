<?php
// student_dashboard.php - Student's Personal Account Dashboard (Files Data is Admin Only)
require_once 'auth_check.php';
require_once 'db_connection.php';
require_once 'file_helper.php';

// If administrator accesses this page, redirect to admin dashboard
if (is_admin()) {
    header("Location: dashboard.php");
    exit();
}

$userId = intval($_SESSION['user_id']);
$userEmail = $_SESSION['email'] ?? '';
$userFullName = $_SESSION['full_name'] ?? $_SESSION['username'];

// 1. Handle Self-Service Form Submissions (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim($_POST['action'] ?? '');

    // Fetch student row first
    $findQuery = $conn->prepare("SELECT * FROM students WHERE user_id = ? OR email = ? LIMIT 1");
    $findQuery->bind_param("is", $userId, $userEmail);
    $findQuery->execute();
    $currStudent = $findQuery->get_result()->fetch_assoc();
    $findQuery->close();

    if ($currStudent) {
        $studentRecordId = $currStudent['id'];
        $studentIdNum = $currStudent['student_id'];

        if ($action === 'update_profile_pic') {
            // Remove profile picture request
            if (isset($_POST['remove_pic']) && $_POST['remove_pic'] == '1') {
                if (!empty($currStudent['profile_picture'])) {
                    delete_record_file($currStudent['profile_picture']);
                    $conn->query("UPDATE students SET profile_picture = NULL WHERE id = " . intval($studentRecordId));
                    $_SESSION['success'] = "Profile picture removed successfully.";
                }
            }
            // Upload new profile picture
            elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                $picError = null;
                $newPic = handle_profile_picture_upload($_FILES['profile_picture'], $studentIdNum, $picError);
                if ($newPic !== false) {
                    if (!empty($currStudent['profile_picture'])) {
                        delete_record_file($currStudent['profile_picture']);
                    }
                    $upStmt = $conn->prepare("UPDATE students SET profile_picture = ? WHERE id = ?");
                    $upStmt->bind_param("si", $newPic, $studentRecordId);
                    $upStmt->execute();
                    $upStmt->close();
                    $_SESSION['success'] = "Profile picture updated successfully.";
                } else {
                    $_SESSION['error'] = "Profile picture upload failed: " . ($picError ?: "Invalid image.");
                }
            }
        } elseif ($action === 'submit_file_request') {
            $docType = trim($_POST['document_type'] ?? '');
            if ($docType === 'Other' && !empty($_POST['custom_document_type'])) {
                $docType = trim($_POST['custom_document_type']);
            }
            $purpose = trim($_POST['purpose'] ?? '');

            if (empty($docType) || empty($purpose)) {
                $_SESSION['error'] = "Please select a document type and provide the purpose for your request.";
            } else {
                $supportingFile = null;
                if (isset($_FILES['supporting_file']) && $_FILES['supporting_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadErr = null;
                    $supportingFile = handle_request_attachment_upload($_FILES['supporting_file'], 'req_' . $studentIdNum, $uploadErr);
                    if ($supportingFile === false) {
                        $_SESSION['error'] = "Attachment upload failed: " . ($uploadErr ?: "Invalid file.");
                        header("Location: student_dashboard.php");
                        exit();
                    }
                }

                $reqStmt = $conn->prepare("INSERT INTO file_requests (student_id, user_id, document_type, purpose, supporting_file, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                $reqStmt->bind_param("iisss", $studentRecordId, $userId, $docType, $purpose, $supportingFile);
                if ($reqStmt->execute()) {
                    $_SESSION['success'] = "File request for '$docType' has been submitted! Status is currently Pending.";
                } else {
                    $_SESSION['error'] = "Failed to submit file request: " . $conn->error;
                }
                $reqStmt->close();
            }
        } elseif ($action === 'cancel_file_request') {
            $reqId = intval($_POST['request_id'] ?? 0);
            if ($reqId > 0) {
                $chkQuery = $conn->prepare("SELECT supporting_file FROM file_requests WHERE id = ? AND student_id = ? AND status = 'Pending'");
                $chkQuery->bind_param("ii", $reqId, $studentRecordId);
                $chkQuery->execute();
                $chkRes = $chkQuery->get_result();
                if ($reqRow = $chkRes->fetch_assoc()) {
                    if (!empty($reqRow['supporting_file'])) {
                        delete_record_file($reqRow['supporting_file']);
                    }
                    $delStmt = $conn->prepare("DELETE FROM file_requests WHERE id = ?");
                    $delStmt->bind_param("i", $reqId);
                    $delStmt->execute();
                    $delStmt->close();
                    $_SESSION['success'] = "Your pending file request has been cancelled.";
                } else {
                    $_SESSION['error'] = "Unable to cancel request. It may have already been reviewed or not found.";
                }
                $chkQuery->close();
            }
        }
    }

    header("Location: student_dashboard.php");
    exit();
}

// 2. Fetch or Initialize Student's Record
$studentStmt = $conn->prepare("SELECT * FROM students WHERE user_id = ? OR email = ? LIMIT 1");
$studentStmt->bind_param("is", $userId, $userEmail);
$studentStmt->execute();
$studentRes = $studentStmt->get_result();
$student = null;

if ($studentRes && $studentRes->num_rows > 0) {
    $student = $studentRes->fetch_assoc();
    // Link user_id if not yet linked
    if (empty($student['user_id'])) {
        $linkStmt = $conn->prepare("UPDATE students SET user_id = ? WHERE id = ?");
        $linkStmt->bind_param("ii", $userId, $student['id']);
        $linkStmt->execute();
        $linkStmt->close();
        $student['user_id'] = $userId;
    }
} else {
    // Initialize student profile from users record
    $uStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $uStmt->bind_param("i", $userId);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();

    $nameParts = explode(' ', trim($uRow['full_name'] ?? 'Student User'));
    $first = $nameParts[0];
    $last  = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : 'Student';
    $stdId = '2026' . sprintf('%03d', $userId);
    $email = $uRow['email'] ?? ($uRow['username'] . '@student.edu');
    $course = 'BSIT';
    $yearLevel = 1;
    $status = 'Regular';

    $createStmt = $conn->prepare("INSERT INTO students (user_id, student_id, first_name, last_name, email, course, year_level, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $createStmt->bind_param("isssssis", $userId, $stdId, $first, $last, $email, $course, $yearLevel, $status);
    $createStmt->execute();
    $newId = $createStmt->insert_id;
    $createStmt->close();

    $fetchAgain = $conn->query("SELECT * FROM students WHERE id = " . intval($newId));
    $student = $fetchAgain ? $fetchAgain->fetch_assoc() : null;
}
$studentStmt->close();

// 3. Fetch Student's File Requests & Status Counts
$fileRequests = [];
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

if ($student && !empty($student['id'])) {
    $reqStmt = $conn->prepare("SELECT * FROM file_requests WHERE student_id = ? ORDER BY id DESC");
    $reqStmt->bind_param("i", $student['id']);
    $reqStmt->execute();
    $reqRes = $reqStmt->get_result();
    while ($row = $reqRes->fetch_assoc()) {
        $fileRequests[] = $row;
        if ($row['status'] === 'Pending') {
            $pendingCount++;
        } elseif ($row['status'] === 'Approved') {
            $approvedCount++;
        } elseif ($row['status'] === 'Rejected') {
            $rejectedCount++;
        }
    }
    $reqStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Student Account - Student Request Records System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            /* Blue and Orange Gradient Background */
            background: linear-gradient(135deg, #0d3b66 0%, #1e40af 45%, #ea580c 100%);
            background-attachment: fixed;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        .navbar-custom {
            background: linear-gradient(90deg, #0d3b66 0%, #1e40af 55%, #ea580c 100%) !important;
        }
        .card-custom {
            border: none;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            background: #ffffff;
            border-top: 4px solid #ea580c;
        }
        .profile-hero {
            background: #ffffff;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.18);
            border-left: 6px solid #ea580c;
        }
        .student-avatar-lg {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #ea580c;
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }
        .student-initials-lg {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 2.5rem;
            background: #e2e8f0;
            color: #1e40af;
            border: 4px solid #ea580c;
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }
        .btn-orange {
            background: #ea580c;
            color: #ffffff;
            border: none;
            font-weight: 600;
        }
        .btn-orange:hover {
            background: #c2410c;
            color: #ffffff;
        }
        .btn-gradient {
            background: linear-gradient(135deg, #1d4ed8 0%, #ea580c 100%);
            border: none;
            color: #ffffff;
            font-weight: 600;
        }
        .btn-gradient:hover {
            opacity: 0.92;
            color: #ffffff;
        }
        .doc-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
        }
        .info-label {
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }
        .info-value {
            font-size: 1.05rem;
            font-weight: 600;
            color: #1e293b;
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center fw-bold" href="student_dashboard.php">
            <i class="bi bi-mortarboard-fill me-2 fs-4 text-warning"></i>
            Student Request Records System
        </a>

        <div class="d-flex align-items-center text-white">
            <span class="badge bg-warning text-dark fw-bold px-2 py-1 me-3 shadow-sm">
                <i class="bi bi-person-badge-fill me-1"></i>STUDENT ACCOUNT
            </span>
            <span class="me-3 d-none d-md-inline">
                Welcome, <strong class="text-white"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></strong>
            </span>
            <a href="logout.php" class="btn btn-outline-light btn-sm px-3">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center shadow" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($_SESSION['success']); ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center shadow" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($_SESSION['error']); ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Student Hero Profile Banner -->
    <div class="profile-hero mb-4">
        <div class="row align-items-center g-4">
            <!-- Profile Picture Section -->
            <div class="col-md-auto text-center">
                <?php if (!empty($student['profile_picture']) && record_file_exists($student['profile_picture'])): ?>
                    <a href="download_file.php?file=<?= urlencode($student['profile_picture']); ?>&action=view" target="_blank" title="View Full Profile Photo">
                        <img src="download_file.php?file=<?= urlencode($student['profile_picture']); ?>&action=view" 
                             alt="My Profile Picture" class="student-avatar-lg">
                    </a>
                <?php else: ?>
                    <div class="student-initials-lg">
                        <?= strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changePhotoModal">
                        <i class="bi bi-camera me-1"></i> Change Photo
                    </button>
                </div>
            </div>

            <!-- Student Summary Info -->
            <div class="col-md">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <h2 class="fw-bold mb-0 text-dark">
                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                    </h2>
                    <?php if ($student['status'] === 'Regular'): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                            <i class="bi bi-check2-circle me-1"></i>Regular Student
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                            <i class="bi bi-exclamation-circle me-1"></i>Irregular Student
                        </span>
                    <?php endif; ?>
                </div>

                <div class="d-flex flex-wrap gap-3 text-muted mb-3">
                    <div>
                        <i class="bi bi-hash text-primary me-1"></i>Student ID: 
                        <strong class="text-primary"><?= htmlspecialchars($student['student_id']); ?></strong>
                    </div>
                    <div>
                        <i class="bi bi-journal-bookmark-fill text-danger me-1"></i>Program: 
                        <strong><?= htmlspecialchars($student['course']); ?></strong>
                    </div>
                    <div>
                        <i class="bi bi-mortarboard-fill text-warning me-1"></i>Year Level: 
                        <strong>Year <?= htmlspecialchars($student['year_level']); ?></strong>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-orange shadow-sm" data-bs-toggle="modal" data-bs-target="#newRequestModal">
                        <i class="bi bi-file-earmark-plus-fill me-1"></i> Request a File
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Academic Profile Details Card -->
        <div class="col-lg-5">
            <div class="card card-custom h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-person-lines-fill text-primary me-2"></i>My Academic Profile
                    </h5>
                    <span class="badge bg-light text-dark border">Personal Record</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="info-label">Student ID Number</div>
                            <div class="info-value text-primary"><?= htmlspecialchars($student['student_id']); ?></div>
                        </div>

                        <div class="col-sm-6">
                            <div class="info-label">Enrollment Status</div>
                            <div class="info-value"><?= htmlspecialchars($student['status']); ?></div>
                        </div>

                        <div class="col-sm-12">
                            <div class="info-label">Registered Email Address</div>
                            <div class="info-value text-break">
                                <a href="mailto:<?= htmlspecialchars($student['email']); ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($student['email']); ?>
                                </a>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="info-label">Degree / Course</div>
                            <div class="info-value">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 px-2 py-1">
                                    <?= htmlspecialchars($student['course']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="info-label">Current Year Level</div>
                            <div class="info-value">Year <?= htmlspecialchars($student['year_level']); ?></div>
                        </div>

                        <div class="col-sm-6">
                            <div class="info-label">Portal Username</div>
                            <div class="info-value text-muted">@<?= htmlspecialchars($_SESSION['username']); ?></div>
                        </div>

                        <div class="col-sm-6">
                            <div class="info-label">Member Since</div>
                            <div class="info-value small text-muted"><?= htmlspecialchars($student['created_at']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- File Requests Card (Replaces My Files Data) -->
        <div class="col-lg-7">
            <div class="card card-custom h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="bi bi-file-earmark-arrow-up-fill text-warning me-2"></i>My File Requests
                        </h5>
                        <span class="badge bg-light text-dark border ms-2">
                            <?= count($fileRequests); ?> Request<?= count($fileRequests) === 1 ? '' : 's'; ?>
                        </span>
                    </div>
                    <button type="button" class="btn btn-sm btn-orange shadow-sm" data-bs-toggle="modal" data-bs-target="#newRequestModal">
                        <i class="bi bi-plus-circle-fill me-1"></i> Request a File
                    </button>
                </div>
                <div class="card-body p-3 p-md-4">
                    <!-- Status Summary Badges -->
                    <div class="d-flex flex-wrap gap-2 mb-3 pb-3 border-bottom">
                        <div class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 fs-6">
                            <i class="bi bi-hourglass-split me-1"></i>Pending: <strong><?= $pendingCount; ?></strong>
                        </div>
                        <div class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
                            <i class="bi bi-check-circle-fill me-1"></i>Approved: <strong><?= $approvedCount; ?></strong>
                        </div>
                        <?php if ($rejectedCount > 0): ?>
                            <div class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fs-6">
                                <i class="bi bi-x-circle-fill me-1"></i>Rejected: <strong><?= $rejectedCount; ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (count($fileRequests) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr class="small text-uppercase text-secondary">
                                        <th>Document</th>
                                        <th>Purpose / Details</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-end">Result / Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fileRequests as $req): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark">
                                                    <i class="bi bi-file-earmark-text text-primary me-1"></i>
                                                    <?= htmlspecialchars($req['document_type']); ?>
                                                </div>
                                                <small class="text-muted d-block">
                                                    <i class="bi bi-calendar3 me-1"></i><?= date('M d, Y h:i A', strtotime($req['created_at'])); ?>
                                                </small>
                                                <?php if (!empty($req['supporting_file']) && record_file_exists($req['supporting_file'])): ?>
                                                    <div class="mt-1">
                                                        <a href="download_file.php?file=<?= urlencode($req['supporting_file']); ?>&action=view" target="_blank" class="badge bg-light text-primary border text-decoration-none">
                                                            <i class="bi bi-paperclip me-1"></i>Attached Requirement
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="small text-dark text-break"><?= htmlspecialchars($req['purpose'] ?: 'None specified'); ?></div>
                                                <?php if (!empty($req['admin_notes'])): ?>
                                                    <div class="mt-1 p-2 bg-light rounded border small">
                                                        <strong class="text-primary"><i class="bi bi-chat-left-dots me-1"></i>Registrar:</strong>
                                                        <span class="text-secondary"><?= htmlspecialchars($req['admin_notes']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?= get_request_status_badge($req['status']); ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($req['status'] === 'Approved'): ?>
                                                    <?php if (!empty($req['approved_file']) && record_file_exists($req['approved_file'])): ?>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="download_file.php?file=<?= urlencode($req['approved_file']); ?>&action=view" target="_blank" class="btn btn-outline-primary" title="View Document">
                                                                <i class="bi bi-eye"></i> View
                                                            </a>
                                                            <a href="download_file.php?file=<?= urlencode($req['approved_file']); ?>&action=download" class="btn btn-success" title="Download Document">
                                                                <i class="bi bi-download"></i> Get File
                                                            </a>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle" title="Document is approved and available at Registrar Office">
                                                            <i class="bi bi-building me-1"></i>Claim at Registrar
                                                        </span>
                                                    <?php endif; ?>
                                                <?php elseif ($req['status'] === 'Pending'): ?>
                                                    <form method="POST" action="student_dashboard.php" class="d-inline" onsubmit="return confirm('Cancel this pending file request?');">
                                                        <input type="hidden" name="action" value="cancel_file_request">
                                                        <input type="hidden" name="request_id" value="<?= $req['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel this pending request">
                                                            <i class="bi bi-trash"></i> Cancel
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small fst-italic">Request Closed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-file-earmark-plus display-4 text-warning mb-2 d-block"></i>
                            <h6 class="fw-bold text-dark">No File Requests Submitted</h6>
                            <p class="small text-muted mb-3">Need official transcripts, certificate of enrollment, or grades? Submit a request directly to the administrator.</p>
                            <button type="button" class="btn btn-sm btn-orange shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#newRequestModal">
                                <i class="bi bi-plus-circle me-1"></i> Submit File Request
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Change Profile Photo -->
<div class="modal fade" id="changePhotoModal" tabindex="-1" aria-labelledby="changePhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white" style="background: linear-gradient(135deg, #1e40af 0%, #ea580c 100%) !important;">
                <h5 class="modal-title fw-bold" id="changePhotoModalLabel">
                    <i class="bi bi-camera-fill me-2"></i>My Profile Picture
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="student_dashboard.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile_pic">
                <div class="modal-body p-4">
                    <div class="text-center mb-3">
                        <?php if (!empty($student['profile_picture']) && record_file_exists($student['profile_picture'])): ?>
                            <img src="download_file.php?file=<?= urlencode($student['profile_picture']); ?>&action=view" 
                                 class="student-avatar-lg mb-2" id="modalPicPreview" alt="Current Photo">
                        <?php else: ?>
                            <div class="student-initials-lg mb-2" id="modalPicInitials">
                                <?= strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <label class="form-label fw-semibold">Choose New Photo (JPG, PNG, WEBP, GIF)</label>
                    <input type="file" class="form-control" name="profile_picture" id="student_photo_input"
                           accept=".jpg,.jpeg,.png,.webp,.gif" onchange="previewStudentPhoto(this)">
                    <div class="form-text">Maximum file size: 3MB. Used as your account avatar.</div>

                    <?php if (!empty($student['profile_picture'])): ?>
                        <div class="form-check mt-3 pt-2 border-top">
                            <input class="form-check-input" type="checkbox" name="remove_pic" value="1" id="removePhotoCheck">
                            <label class="form-check-label text-danger small fw-semibold" for="removePhotoCheck">
                                <i class="bi bi-trash me-1"></i> Remove my current profile picture
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-orange fw-semibold">Save Photo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Submit New File Request -->
<div class="modal fade" id="newRequestModal" tabindex="-1" aria-labelledby="newRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #1e40af 0%, #ea580c 100%) !important;">
                <h5 class="modal-title fw-bold" id="newRequestModalLabel">
                    <i class="bi bi-file-earmark-plus-fill me-2"></i>Request a Document / File
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="student_dashboard.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_file_request">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Document to Request <span class="text-danger">*</span></label>
                        <select class="form-select" name="document_type" id="docTypeSelect" onchange="toggleCustomDocInput(this)" required>
                            <option value="" disabled selected>-- Choose Document --</option>
                            <option value="Transcript of Records (TOR)">Transcript of Records (TOR)</option>
                            <option value="Certificate of Enrollment / Registration (COR)">Certificate of Enrollment / Registration (COR)</option>
                            <option value="Certificate of Grades (COG)">Certificate of Grades (COG)</option>
                            <option value="Certificate of Good Moral Character">Certificate of Good Moral Character</option>
                            <option value="Student Clearance">Student Clearance</option>
                            <option value="Honorable Dismissal">Honorable Dismissal</option>
                            <option value="Diploma Copy / Certificate of Graduation">Diploma Copy / Certificate of Graduation</option>
                            <option value="Course Curriculum / Program Checklist">Course Curriculum / Program Checklist</option>
                            <option value="Other">Other Document (Specify below)</option>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="customDocGroup">
                        <label class="form-label fw-semibold">Specify Document Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="custom_document_type" id="customDocInput" placeholder="e.g. Certificate of Candidacy for Graduation">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Purpose / Reason of Request <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="purpose" rows="3" placeholder="e.g. Scholarship application requirement, employment verification, board exam, transfer, or personal copy..." required></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold d-flex justify-content-between align-items-center mb-1">
                            <span>Optional Supporting Attachment</span>
                            <span class="badge bg-light text-secondary border">Optional</span>
                        </label>
                        <input type="file" class="form-control" name="supporting_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.zip">
                        <div class="form-text">Attach clearance slip, school ID copy, or receipt if required (Max 5MB).</div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-orange fw-semibold">
                        <i class="bi bi-send-fill me-1"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewStudentPhoto(input) {
    const file = input.files[0];
    if (file) {
        if (file.size > 3 * 1024 * 1024) {
            alert("Profile picture exceeds the maximum allowed limit of 3MB.");
            input.value = "";
            return;
        }
        const preview = document.getElementById('modalPicPreview');
        if (preview) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }
}

function toggleCustomDocInput(select) {
    const customGroup = document.getElementById('customDocGroup');
    const customInput = document.getElementById('customDocInput');
    if (select.value === 'Other') {
        customGroup.classList.remove('d-none');
        customInput.setAttribute('required', 'required');
        customInput.focus();
    } else {
        customGroup.classList.add('d-none');
        customInput.removeAttribute('required');
    }
}
</script>
</body>
</html>

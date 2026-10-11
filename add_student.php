<?php
// add_student.php - Handle Adding a Student (Admin Only)
require_once 'auth_check.php';
require_admin(); // Enforce Admin Only Access
require_once 'db_connection.php';
require_once 'file_helper.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $student_id = trim($_POST['student_id'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $course     = trim($_POST['course'] ?? '');
    $year_level = intval($_POST['year_level'] ?? 0);
    $status     = trim($_POST['status'] ?? 'Regular');

    // Validation
    if (empty($student_id) || empty($first_name) || empty($last_name) || empty($email) || empty($course) || $year_level <= 0) {
        $_SESSION['error'] = "All required fields must be filled out correctly.";
        header("Location: dashboard.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format. Please provide a valid email address.";
        header("Location: dashboard.php");
        exit();
    }

    // Check Student ID uniqueness
    $checkSql = "SELECT id FROM students WHERE student_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $student_id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $_SESSION['error'] = "Student ID '$student_id' already exists. Please use a unique Student ID.";
        $checkStmt->close();
        header("Location: dashboard.php");
        exit();
    }
    $checkStmt->close();

    // 1. Handle Profile Picture Upload (Images Only)
    $profilePicName = null;
    $picError = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadPic = handle_profile_picture_upload($_FILES['profile_picture'], $student_id, $picError);
        if ($uploadPic === false) {
            $_SESSION['error'] = "Profile picture error: " . ($picError ?: "Invalid image.");
            header("Location: dashboard.php");
            exit();
        }
        $profilePicName = $uploadPic;
    }

    // 2. Handle Record File Upload (Files Data Only)
    $recordFileName = null;
    $fileError = null;
    if (isset($_FILES['record_file']) && $_FILES['record_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadFile = handle_record_file_upload($_FILES['record_file'], $student_id, $fileError);
        if ($uploadFile === false) {
            if ($profilePicName) {
                delete_record_file($profilePicName);
            }
            $_SESSION['error'] = "Record file error: " . ($fileError ?: "Invalid data file.");
            header("Location: dashboard.php");
            exit();
        }
        $recordFileName = $uploadFile;
    }

    // Insert student record
    $insertSql = "INSERT INTO students (student_id, first_name, last_name, email, course, year_level, status, profile_picture, record_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertSql);
    $stmt->bind_param("sssssisss", $student_id, $first_name, $last_name, $email, $course, $year_level, $status, $profilePicName, $recordFileName);

    if ($stmt->execute()) {
        $msg = "Student record added successfully by Administrator.";
        if ($profilePicName && $recordFileName) {
            $msg .= " Profile picture and record file attached.";
        } elseif ($profilePicName) {
            $msg .= " Profile picture uploaded.";
        } elseif ($recordFileName) {
            $msg .= " Record file attached.";
        }
        $_SESSION['success'] = $msg;
    } else {
        if ($profilePicName) delete_record_file($profilePicName);
        if ($recordFileName) delete_record_file($recordFileName);
        $_SESSION['error'] = "Failed to add student record: " . $conn->error;
    }

    $stmt->close();
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student Record (Admin) - Student Request Records System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0d3b66 0%, #1e40af 45%, #ea580c 100%);
            background-attachment: fixed;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        .navbar-custom {
            background: linear-gradient(90deg, #0d3b66 0%, #1e40af 55%, #ea580c 100%);
        }
        .card-custom {
            border: none;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            background: #ffffff;
            border-top: 4px solid #ea580c;
        }
        .card-header-custom {
            background: linear-gradient(135deg, #1e40af 0%, #ea580c 100%);
            color: #ffffff;
            border-radius: 12px 12px 0 0 !important;
            padding: 18px 24px;
        }
        .form-label {
            font-weight: 600;
            color: #334155;
            font-size: 0.9rem;
        }
        .upload-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px;
        }
        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ea580c;
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
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-mortarboard-fill me-2"></i>Student Request Records System
        </a>
        <div class="d-flex align-items-center">
            <span class="badge bg-warning text-dark me-2">
                <i class="bi bi-shield-lock-fill me-1"></i>Administrator
            </span>
            <span class="text-white-50 me-3 small">
                Logged in as: <strong class="text-white"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></strong>
            </span>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
            <a href="logout.php" class="btn btn-danger btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card card-custom">
                <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0 fw-bold">
                            <i class="bi bi-shield-check me-2"></i>Add Student Record
                        </h4>
                        <small class="text-white-50">Administrator Mode &bull; Register credentials, profile photo, and data files</small>
                    </div>
                    <a href="dashboard.php" class="btn btn-sm btn-light text-primary fw-semibold">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <div class="card-body p-4">
                    <form method="POST" action="add_student.php" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Student ID <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                                    <input type="text" class="form-control" name="student_id" placeholder="e.g. 2026006" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Course / Program <span class="text-danger">*</span></label>
                                <select class="form-select" name="course" required>
                                    <option value="" disabled selected>Select Course</option>
                                    <option value="BSIT">BSIT - Bachelor of Science in Information Technology</option>
                                    <option value="BSCS">BSCS - Bachelor of Science in Computer Science</option>
                                    <option value="BSIS">BSIS - Bachelor of Science in Information Systems</option>
                                    <option value="BSEMC">BSEMC - BS in Entertainment and Multimedia Computing</option>
                                    <option value="BSBA">BSBA - Bachelor of Science in Business Administration</option>
                                    <option value="BSCPE">BSCPE - Bachelor of Science in Computer Engineering</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="first_name" placeholder="e.g. John" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="last_name" placeholder="e.g. Doe" required>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" name="email" placeholder="e.g. john.doe@example.com" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Year Level <span class="text-danger">*</span></label>
                                <select class="form-select" name="year_level" required>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Enrollment Status <span class="text-danger">*</span></label>
                                <select class="form-select" name="status" required>
                                    <option value="Regular" selected>Regular</option>
                                    <option value="Irregular">Irregular</option>
                                </select>
                            </div>

                            <!-- 1. SEPARATE PROFILE PICTURE SECTION -->
                            <div class="col-md-12 mt-4">
                                <div class="upload-section">
                                    <label class="form-label d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-primary fw-bold">
                                            <i class="bi bi-person-bounding-box me-1"></i> Profile Picture (Photo Only)
                                        </span>
                                        <span class="badge bg-light text-secondary border">Optional</span>
                                    </label>
                                    <div class="row align-items-center g-3">
                                        <div class="col-auto text-center" id="picPreviewCol" style="display: none;">
                                            <img id="pagePicPreview" src="#" alt="Profile Preview" class="avatar-preview shadow-sm">
                                        </div>
                                        <div class="col">
                                            <input type="file" class="form-control" name="profile_picture" id="page_profile_pic"
                                                   accept=".jpg,.jpeg,.png,.webp,.gif"
                                                   onchange="previewPageProfilePic(this)">
                                            <div class="form-text">
                                                Accepted image formats: JPG, PNG, WEBP, GIF (Max size: 3MB).
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. SEPARATE FILES DATA SECTION -->
                            <div class="col-md-12 mt-3">
                                <div class="upload-section">
                                    <label class="form-label d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-success fw-bold">
                                            <i class="bi bi-file-earmark-arrow-up-fill me-1"></i> Files Data (Documents / Data Only)
                                        </span>
                                        <span class="badge bg-light text-secondary border">Optional</span>
                                    </label>
                                    <input type="file" class="form-control" name="record_file" id="page_record_file"
                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip"
                                           onchange="previewPageRecordFile(this)">
                                    <div class="form-text">
                                        Accepted files data: PDF, Word (DOC/DOCX), Excel (XLS/XLSX/CSV), Text (TXT), ZIP (Max size: 5MB).
                                    </div>

                                    <div id="pageFilePreviewBox" class="mt-2 p-2 bg-white rounded border d-none">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-check-fill text-success fs-3 me-2"></i>
                                                <div class="small">
                                                    <strong id="pageFileName" class="text-dark"></strong>
                                                    <span id="pageFileSize" class="text-muted ms-1"></span>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearPageRecordFileInput()">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-gradient px-4 shadow-sm">
                                <i class="bi bi-check-circle-fill me-1"></i> Save Student Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewPageProfilePic(input) {
    const file = input.files[0];
    const previewCol = document.getElementById('picPreviewCol');
    const previewImg = document.getElementById('pagePicPreview');

    if (file) {
        if (file.size > 3 * 1024 * 1024) {
            alert("Profile picture exceeds the maximum allowed limit of 3MB.");
            input.value = "";
            previewCol.style.display = "none";
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewCol.style.display = "block";
        };
        reader.readAsDataURL(file);
    } else {
        previewCol.style.display = "none";
    }
}

function previewPageRecordFile(input) {
    const file = input.files[0];
    const previewBox = document.getElementById('pageFilePreviewBox');
    const nameLabel = document.getElementById('pageFileName');
    const sizeLabel = document.getElementById('pageFileSize');

    if (file) {
        if (file.size > 5 * 1024 * 1024) {
            alert("Record file exceeds the maximum allowed limit of 5MB.");
            input.value = "";
            previewBox.classList.add('d-none');
            return;
        }
        nameLabel.textContent = file.name;
        sizeLabel.textContent = '(' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
        previewBox.classList.remove('d-none');
    } else {
        previewBox.classList.add('d-none');
    }
}

function clearPageRecordFileInput() {
    const input = document.getElementById('page_record_file');
    input.value = '';
    document.getElementById('pageFilePreviewBox').classList.add('d-none');
}
</script>
</body>
</html>

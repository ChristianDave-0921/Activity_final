<?php
// edit_student.php - Edit Student with Separate Profile Picture and Files Data (Admin Only)
require_once 'auth_check.php';
require_admin(); // Enforce Admin Only Access
require_once 'db_connection.php';
require_once 'file_helper.php';

$error = "";

// 1. Handle Form Submission (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id          = intval($_POST['id'] ?? 0);
    $student_id  = trim($_POST['student_id'] ?? '');
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $course      = trim($_POST['course'] ?? '');
    $year_level  = intval($_POST['year_level'] ?? 0);
    $status      = trim($_POST['status'] ?? 'Regular');
    
    $remove_pic  = isset($_POST['remove_profile_picture']) && $_POST['remove_profile_picture'] == '1';
    $remove_file = isset($_POST['remove_record_file']) && $_POST['remove_record_file'] == '1';

    // Validation
    if ($id <= 0 || empty($student_id) || empty($first_name) || empty($last_name) || empty($email) || empty($course) || $year_level <= 0) {
        $error = "All required fields must be filled out.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Validate Student ID uniqueness (excluding current student record)
        $checkSql = "SELECT id FROM students WHERE student_id = ? AND id != ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("si", $student_id, $id);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $error = "Student ID '$student_id' is already assigned to another student. Student ID must remain unique.";
            $checkStmt->close();
        } else {
            $checkStmt->close();

            // Fetch current files from DB
            $currPic  = null;
            $currFile = null;
            $fetchCurrent = $conn->prepare("SELECT profile_picture, record_file FROM students WHERE id = ?");
            $fetchCurrent->bind_param("i", $id);
            $fetchCurrent->execute();
            $currRes = $fetchCurrent->get_result();
            if ($currRes && $row = $currRes->fetch_assoc()) {
                $currPic  = $row['profile_picture'];
                $currFile = $row['record_file'];
            }
            $fetchCurrent->close();

            $picToSave  = $currPic;
            $fileToSave = $currFile;

            // Handle Profile Picture removal
            if ($remove_pic && !empty($currPic)) {
                delete_record_file($currPic);
                $picToSave = null;
            }

            // Handle Record File removal
            if ($remove_file && !empty($currFile)) {
                delete_record_file($currFile);
                $fileToSave = null;
            }

            // Handle New Profile Picture upload (Images Only)
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                $picError = null;
                $newPic = handle_profile_picture_upload($_FILES['profile_picture'], $student_id, $picError);
                if ($newPic === false) {
                    $error = "Profile picture upload failed: " . ($picError ?: "Invalid image.");
                } else {
                    if (!empty($currPic)) {
                        delete_record_file($currPic);
                    }
                    $picToSave = $newPic;
                }
            }

            // Handle New Record File upload (Files Data Only)
            if (empty($error) && isset($_FILES['record_file']) && $_FILES['record_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $fileError = null;
                $newFile = handle_record_file_upload($_FILES['record_file'], $student_id, $fileError);
                if ($newFile === false) {
                    $error = "Record file upload failed: " . ($fileError ?: "Invalid data file.");
                } else {
                    if (!empty($currFile)) {
                        delete_record_file($currFile);
                    }
                    $fileToSave = $newFile;
                }
            }

            if (empty($error)) {
                $updateSql = "UPDATE students SET student_id = ?, first_name = ?, last_name = ?, email = ?, course = ?, year_level = ?, status = ?, profile_picture = ?, record_file = ? WHERE id = ?";
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param("sssssisssi", $student_id, $first_name, $last_name, $email, $course, $year_level, $status, $picToSave, $fileToSave, $id);

                if ($stmt->execute()) {
                    $stmt->close();
                    $_SESSION['success'] = "Student record updated successfully by Administrator.";
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Failed to update record: " . $conn->error;
                    $stmt->close();
                }
            }
        }
    }
}

// 2. Handle Displaying Current Information (GET)
$student_id_val      = "";
$first_name_val      = "";
$last_name_val       = "";
$email_val           = "";
$course_val          = "";
$year_level_val      = 1;
$status_val          = "Regular";
$profile_picture_val = null;
$record_file_val     = null;
$id_val              = 0;

$id_param = intval($_GET['id'] ?? ($_POST['id'] ?? 0));

if ($id_param > 0) {
    $fetchSql = "SELECT * FROM students WHERE id = ?";
    $fetchStmt = $conn->prepare($fetchSql);
    $fetchStmt->bind_param("i", $id_param);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();

    if ($result && $result->num_rows === 1) {
        $student = $result->fetch_assoc();
        $id_val              = $student['id'];
        $student_id_val      = $student['student_id'];
        $first_name_val      = $student['first_name'];
        $last_name_val       = $student['last_name'];
        $email_val           = $student['email'];
        $course_val          = $student['course'];
        $year_level_val      = $student['year_level'];
        $status_val          = $student['status'];
        $profile_picture_val = $student['profile_picture'] ?? null;
        $record_file_val     = $student['record_file'] ?? null;
    } else {
        $_SESSION['error'] = "Student record not found.";
        header("Location: dashboard.php");
        exit();
    }
    $fetchStmt->close();
} else {
    $_SESSION['error'] = "Invalid student record ID.";
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student (Admin) - Student Request Records System</title>
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
        .section-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px;
        }
        .avatar-lg {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ea580c;
        }
        .avatar-initials-lg {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.8rem;
            background-color: #e2e8f0;
            color: #475569;
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

            <!-- Breadcrumb Navigation -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb bg-white p-2 rounded shadow-sm">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Student Record</li>
                </ol>
            </nav>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                    <div><?= htmlspecialchars($error); ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card card-custom">
                <div class="card-header card-header-custom d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="mb-0 fw-bold">
                            <i class="bi bi-pencil-square me-2"></i>Edit Student Information
                        </h5>
                        <small class="text-white-50">Administrator Mode &bull; Modify student details, photo, and files data</small>
                    </div>
                    <span class="badge bg-light text-dark">ID: <?= htmlspecialchars($student_id_val); ?></span>
                </div>

                <div class="card-body p-4">
                    <form method="POST" action="edit_student.php" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($id_val); ?>">

                        <div class="row g-3">
                            <!-- Student ID -->
                            <div class="col-md-6">
                                <label for="student_id" class="form-label">
                                    Student ID <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                    <input type="text" class="form-control" id="student_id" name="student_id" 
                                           value="<?= htmlspecialchars($student_id_val); ?>" required>
                                </div>
                                <div class="form-text">Must be unique for each student.</div>
                            </div>

                            <!-- Course -->
                            <div class="col-md-6">
                                <label for="course" class="form-label">
                                    Course / Program <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-journal-code"></i></span>
                                    <select class="form-select" id="course" name="course" required>
                                        <option value="" disabled>Select Course</option>
                                        <option value="BSIT" <?= ($course_val === 'BSIT') ? 'selected' : ''; ?>>BSIT - Bachelor of Science in Information Technology</option>
                                        <option value="BSCS" <?= ($course_val === 'BSCS') ? 'selected' : ''; ?>>BSCS - Bachelor of Science in Computer Science</option>
                                        <option value="BSIS" <?= ($course_val === 'BSIS') ? 'selected' : ''; ?>>BSIS - Bachelor of Science in Information Systems</option>
                                        <option value="BSEMC" <?= ($course_val === 'BSEMC') ? 'selected' : ''; ?>>BSEMC - BS in Entertainment and Multimedia Computing</option>
                                        <option value="BSBA" <?= ($course_val === 'BSBA') ? 'selected' : ''; ?>>BSBA - Bachelor of Science in Business Administration</option>
                                        <option value="BSCPE" <?= ($course_val === 'BSCPE') ? 'selected' : ''; ?>>BSCPE - Bachelor of Science in Computer Engineering</option>
                                    </select>
                                </div>
                            </div>

                            <!-- First Name -->
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">
                                    First Name <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($first_name_val); ?>" required>
                                </div>
                            </div>

                            <!-- Last Name -->
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">
                                    Last Name <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($last_name_val); ?>" required>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="col-md-12">
                                <label for="email" class="form-label">
                                    Email Address <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($email_val); ?>" required>
                                </div>
                            </div>

                            <!-- Year Level -->
                            <div class="col-md-6">
                                <label for="year_level" class="form-label">
                                    Year Level <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                    <select class="form-select" id="year_level" name="year_level" required>
                                        <option value="1" <?= ($year_level_val == 1) ? 'selected' : ''; ?>>1st Year</option>
                                        <option value="2" <?= ($year_level_val == 2) ? 'selected' : ''; ?>>2nd Year</option>
                                        <option value="3" <?= ($year_level_val == 3) ? 'selected' : ''; ?>>3rd Year</option>
                                        <option value="4" <?= ($year_level_val == 4) ? 'selected' : ''; ?>>4th Year</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Status -->
                            <div class="col-md-6">
                                <label for="status" class="form-label">
                                    Enrollment Status <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-toggle-on"></i></span>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="Regular" <?= ($status_val === 'Regular') ? 'selected' : ''; ?>>Regular</option>
                                        <option value="Irregular" <?= ($status_val === 'Irregular') ? 'selected' : ''; ?>>Irregular</option>
                                    </select>
                                </div>
                            </div>

                            <!-- 1. SEPARATE PROFILE PICTURE SECTION -->
                            <div class="col-md-12 mt-4">
                                <div class="section-box">
                                    <h6 class="fw-bold text-primary mb-3">
                                        <i class="bi bi-person-bounding-box me-1"></i> Profile Picture (Photo Only)
                                    </h6>
                                    
                                    <div class="row align-items-center g-3 mb-3">
                                        <div class="col-auto">
                                            <?php if (!empty($profile_picture_val) && record_file_exists($profile_picture_val)): ?>
                                                <img src="download_file.php?file=<?= urlencode($profile_picture_val); ?>&action=view" 
                                                     alt="Profile Picture" class="avatar-lg shadow-sm" id="currentPicImg">
                                            <?php else: ?>
                                                <div class="avatar-initials-lg shadow-sm" id="initialsAvatar">
                                                    <?= strtoupper(substr($first_name_val, 0, 1) . substr($last_name_val, 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col">
                                            <?php if (!empty($profile_picture_val) && record_file_exists($profile_picture_val)): ?>
                                                <div class="small fw-bold text-dark text-break"><?= htmlspecialchars($profile_picture_val); ?></div>
                                                <div class="small text-muted mb-2">Size: <?= get_record_file_size($profile_picture_val); ?></div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="remove_profile_picture" value="1" id="removePicCheck">
                                                    <label class="form-check-label text-danger small fw-semibold" for="removePicCheck">
                                                        <i class="bi bi-trash me-1"></i> Remove this profile picture
                                                    </label>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-muted small fst-italic">No profile picture currently set.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <label for="edit_profile_pic" class="form-label small text-muted">
                                        <?= !empty($profile_picture_val) ? 'Upload New Photo to Replace:' : 'Upload Profile Picture:'; ?>
                                    </label>
                                    <input type="file" class="form-control" name="profile_picture" id="edit_profile_pic"
                                           accept=".jpg,.jpeg,.png,.webp,.gif">
                                    <div class="form-text">JPG, PNG, WEBP, GIF (Max 3MB).</div>
                                </div>
                            </div>

                            <!-- 2. SEPARATE FILES DATA SECTION -->
                            <div class="col-md-12 mt-3">
                                <div class="section-box">
                                    <h6 class="fw-bold text-success mb-3">
                                        <i class="bi bi-file-earmark-arrow-up-fill me-1"></i> Files Data (Documents / Data Only)
                                    </h6>

                                    <?php if (!empty($record_file_val) && record_file_exists($record_file_val)): ?>
                                        <div class="bg-white p-3 rounded border mb-3">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="me-2"><?= get_file_type_badge($record_file_val); ?></div>
                                                    <div>
                                                        <div class="fw-bold text-dark text-break"><?= htmlspecialchars($record_file_val); ?></div>
                                                        <div class="small text-muted">Size: <?= get_record_file_size($record_file_val); ?></div>
                                                    </div>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <a href="download_file.php?file=<?= urlencode($record_file_val); ?>&action=view" 
                                                       target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                    <a href="download_file.php?file=<?= urlencode($record_file_val); ?>&action=download" 
                                                       class="btn btn-sm btn-outline-secondary">
                                                        <i class="bi bi-download"></i> Download
                                                    </a>
                                                </div>
                                            </div>

                                            <div class="form-check mt-2 pt-2 border-top">
                                                <input class="form-check-input" type="checkbox" name="remove_record_file" value="1" id="removeFileCheck">
                                                <label class="form-check-label text-danger small fw-semibold" for="removeFileCheck">
                                                    <i class="bi bi-trash me-1"></i> Remove this attached file
                                                </label>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-light border text-muted small py-2 mb-3">
                                            <i class="bi bi-info-circle me-1"></i> No data file currently attached to this student record.
                                        </div>
                                    <?php endif; ?>

                                    <label for="edit_record_file" class="form-label small text-muted">
                                        <?= !empty($record_file_val) ? 'Upload New File to Replace:' : 'Attach Record File:'; ?>
                                    </label>
                                    <input type="file" class="form-control" name="record_file" id="edit_record_file"
                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
                                    <div class="form-text">
                                        Accepted files: PDF, DOC, DOCX, XLS, XLSX, CSV, TXT, ZIP (Max 5MB).
                                    </div>
                                </div>
                            </div>

                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="dashboard.php" class="btn btn-outline-secondary px-4">
                                <i class="bi bi-x-circle me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-gradient px-4 shadow-sm">
                                <i class="bi bi-check2-circle me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

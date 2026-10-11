<?php
// dashboard.php - Student Record System Dashboard with Role-Based Access and Blue & Orange Theme
require_once 'auth_check.php';
require_once 'db_connection.php';
require_once 'file_helper.php';

// Only administrators can access the main management dashboard
if (!is_admin()) {
    header("Location: student_dashboard.php");
    exit();
}

$isAdmin = true;

// Retrieve search and filter inputs
$search        = trim($_GET['search'] ?? '');
$filter_course = trim($_GET['filter_course'] ?? '');
$filter_year   = trim($_GET['filter_year'] ?? '');
$filter_status = trim($_GET['filter_status'] ?? '');

// Statistics queries
$totalStudents  = 0;
$regularCount   = 0;
$irregularCount = 0;
$totalFiles     = 0;

$countRes = $conn->query("SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'Regular' THEN 1 ELSE 0 END) AS regular_count,
    SUM(CASE WHEN status = 'Irregular' THEN 1 ELSE 0 END) AS irregular_count,
    SUM(CASE WHEN record_file IS NOT NULL AND record_file != '' THEN 1 ELSE 0 END) AS files_count
FROM students");

if ($countRes && $row = $countRes->fetch_assoc()) {
    $totalStudents  = (int)($row['total'] ?? 0);
    $regularCount   = (int)($row['regular_count'] ?? 0);
    $irregularCount = (int)($row['irregular_count'] ?? 0);
    $totalFiles     = (int)($row['files_count'] ?? 0);
}

// File Requests Statistics
$totalRequests    = 0;
$pendingRequests  = 0;
$approvedRequests = 0;
$rejectedRequests = 0;

$reqCountRes = $conn->query("SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_count
FROM file_requests");

if ($reqCountRes && $rowR = $reqCountRes->fetch_assoc()) {
    $totalRequests    = (int)($rowR['total'] ?? 0);
    $pendingRequests  = (int)($rowR['pending_count'] ?? 0);
    $approvedRequests = (int)($rowR['approved_count'] ?? 0);
    $rejectedRequests = (int)($rowR['rejected_count'] ?? 0);
}

// Build dynamic student list query with prepared statement
$sql = "SELECT * FROM students WHERE 1=1";
$params = [];
$types  = "";

if (!empty($search)) {
    $sql .= " AND (student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)";
    $searchWildcard = "%" . $search . "%";
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $types   .= "ssss";
}

if (!empty($filter_course)) {
    $sql .= " AND course = ?";
    $params[] = $filter_course;
    $types   .= "s";
}

if (!empty($filter_year)) {
    $sql .= " AND year_level = ?";
    $params[] = intval($filter_year);
    $types   .= "i";
}

if (!empty($filter_status)) {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
    $types   .= "s";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$studentsResult = $stmt->get_result();

$studentsList = [];
while ($row = $studentsResult->fetch_assoc()) {
    $studentsList[] = $row;
}
$stmt->close();

// Query File Requests for Admin Management
$req_status_filter = trim($_GET['req_status'] ?? '');
$reqSql = "SELECT fr.*, s.student_id AS std_num, s.first_name, s.last_name, s.course, s.year_level, s.email AS std_email 
           FROM file_requests fr
           LEFT JOIN students s ON fr.student_id = s.id";
if (!empty($req_status_filter)) {
    $reqSql .= " WHERE fr.status = '" . $conn->real_escape_string($req_status_filter) . "'";
}
$reqSql .= " ORDER BY fr.id DESC";

$allRequests = [];
$allRequestsRes = $conn->query($reqSql);
if ($allRequestsRes) {
    while ($rRow = $allRequestsRes->fetch_assoc()) {
        $allRequests[] = $rRow;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Student Request Records System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            /* Blue and Orange Theme Gradient Background */
            background: linear-gradient(135deg, #0d3b66 0%, #1e40af 45%, #ea580c 100%);
            background-attachment: fixed;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        .navbar-custom {
            background: linear-gradient(90deg, #0d3b66 0%, #1e40af 55%, #ea580c 100%) !important;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .stat-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background: #ffffff;
            border-bottom: 3px solid #ea580c;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.2);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .card-custom {
            border: none;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            background: #ffffff;
            border-top: 4px solid #ea580c;
        }
        .table-custom-header {
            background: linear-gradient(90deg, #0d3b66 0%, #1e40af 60%, #c2410c 100%);
            color: #ffffff;
        }
        .table-custom-header th {
            font-weight: 600;
            vertical-align: middle;
            color: #ffffff;
            border: none;
        }
        .table td {
            vertical-align: middle;
        }
        .action-btns .btn {
            padding: 4px 8px;
            font-size: 0.82rem;
            margin: 2px;
        }
        .student-avatar {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #ea580c;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        .avatar-initials {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            background-color: #e2e8f0;
            color: #1e40af;
            border: 2px solid #cbd5e1;
        }
        .avatar-modal-lg {
            width: 85px;
            height: 85px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #ea580c;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        .modal-initials-lg {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.8rem;
            background-color: #e2e8f0;
            color: #1e40af;
            border: 3px solid #cbd5e1;
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
        .hero-banner {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 14px;
            padding: 20px 24px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            border-left: 5px solid #ea580c;
        }
    </style>
</head>
<body>

<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <i class="bi bi-mortarboard-fill me-2 fs-4 text-warning"></i>
            Student Request Records System
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active fw-semibold" href="dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold" href="#fileRequestsSection">
                        <i class="bi bi-file-earmark-arrow-up-fill me-1"></i> File Requests
                        <?php if ($pendingRequests > 0): ?>
                            <span class="badge bg-warning text-dark rounded-pill ms-1"><?= $pendingRequests; ?> Pending</span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center text-white">
                <div class="me-3 d-flex align-items-center">
                    <?php if ($isAdmin): ?>
                        <span class="badge bg-warning text-dark fw-bold px-2 py-1 me-2 shadow-sm">
                            <i class="bi bi-shield-lock-fill me-1"></i>ADMIN
                        </span>
                    <?php else: ?>
                        <span class="badge bg-light text-dark fw-semibold px-2 py-1 me-2 shadow-sm">
                            <i class="bi bi-person me-1"></i>USER
                        </span>
                    <?php endif; ?>
                    <span class="d-none d-md-inline">Welcome, <strong class="text-white"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></strong></span>
                </div>
                <a href="logout.php" class="btn btn-outline-light btn-sm px-3">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container py-4">

    <!-- Flash Messages (Success & Error Alerts) -->
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

    <!-- Header Actions & Banner -->
    <div class="hero-banner mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">
                <i class="bi bi-grid-1x2-fill text-primary me-2"></i>Student Request Records Dashboard
            </h3>
            <p class="text-muted mb-0 small">
                <?= $isAdmin ? 'Administrator Mode &bull; Full CRUD privileges (Add, Edit, Delete, Files Data)' : 'Standard User Mode &bull; View-only student records and download documents'; ?>
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($isAdmin): ?>
                <button type="button" class="btn btn-orange shadow-sm fw-semibold px-3 py-2" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="bi bi-person-plus-fill me-1"></i> Add New Student
                </button>
            <?php else: ?>
                <span class="badge bg-light text-muted border p-2 shadow-sm d-flex align-items-center">
                    <i class="bi bi-lock-fill text-warning me-1"></i> Add/Edit Restricted to Admin
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card stat-card p-3">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Students</div>
                        <h3 class="fw-bold mb-0 text-dark"><?= $totalStudents; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6">
            <div class="card stat-card p-3">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                        <i class="bi bi-patch-check-fill"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Regular</div>
                        <h3 class="fw-bold mb-0 text-dark"><?= $regularCount; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6">
            <div class="card stat-card p-3">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                        <i class="bi bi-person-exclamation"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Irregular</div>
                        <h3 class="fw-bold mb-0 text-dark"><?= $irregularCount; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6">
            <div class="card stat-card p-3">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                        <i class="bi bi-file-earmark-arrow-up-fill"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Files Data</div>
                        <h3 class="fw-bold mb-0 text-dark"><?= $totalFiles; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6">
            <a href="#fileRequestsSection" class="text-decoration-none">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                            <i class="bi bi-inbox-fill"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase">File Requests</div>
                            <div class="d-flex align-items-center gap-2">
                                <h3 class="fw-bold mb-0 text-dark"><?= $totalRequests; ?></h3>
                                <?php if ($pendingRequests > 0): ?>
                                    <span class="badge bg-warning text-dark small"><?= $pendingRequests; ?> Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="card card-custom mb-4">
        <div class="card-body p-3">
            <form method="GET" action="dashboard.php" class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-12">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search by Student ID or Name..." value="<?= htmlspecialchars($search); ?>">
                    </div>
                </div>

                <div class="col-lg-2 col-md-4 col-sm-6">
                    <select class="form-select" name="filter_course">
                        <option value="">All Courses</option>
                        <option value="BSIT" <?= ($filter_course === 'BSIT') ? 'selected' : ''; ?>>BSIT</option>
                        <option value="BSCS" <?= ($filter_course === 'BSCS') ? 'selected' : ''; ?>>BSCS</option>
                        <option value="BSIS" <?= ($filter_course === 'BSIS') ? 'selected' : ''; ?>>BSIS</option>
                        <option value="BSEMC" <?= ($filter_course === 'BSEMC') ? 'selected' : ''; ?>>BSEMC</option>
                        <option value="BSBA" <?= ($filter_course === 'BSBA') ? 'selected' : ''; ?>>BSBA</option>
                        <option value="BSCPE" <?= ($filter_course === 'BSCPE') ? 'selected' : ''; ?>>BSCPE</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4 col-sm-6">
                    <select class="form-select" name="filter_year">
                        <option value="">All Year Levels</option>
                        <option value="1" <?= ($filter_year === '1') ? 'selected' : ''; ?>>Year 1</option>
                        <option value="2" <?= ($filter_year === '2') ? 'selected' : ''; ?>>Year 2</option>
                        <option value="3" <?= ($filter_year === '3') ? 'selected' : ''; ?>>Year 3</option>
                        <option value="4" <?= ($filter_year === '4') ? 'selected' : ''; ?>>Year 4</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4 col-sm-6">
                    <select class="form-select" name="filter_status">
                        <option value="">All Statuses</option>
                        <option value="Regular" <?= ($filter_status === 'Regular') ? 'selected' : ''; ?>>Regular</option>
                        <option value="Irregular" <?= ($filter_status === 'Irregular') ? 'selected' : ''; ?>>Irregular</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-12 col-sm-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100 fw-semibold">
                        <i class="bi bi-funnel-fill"></i> Filter
                    </button>
                    <?php if (!empty($search) || !empty($filter_course) || !empty($filter_year) || !empty($filter_status)): ?>
                        <a href="dashboard.php" class="btn btn-outline-secondary" title="Clear Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Student Records Table -->
    <div class="card card-custom">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="bi bi-card-list me-2 text-primary"></i>Student Records List
            </h5>
            <span class="badge bg-secondary">
                Showing <?= count($studentsList); ?> record(s)
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-custom-header">
                        <tr>
                            <th class="ps-3 text-center" style="width: 70px;">Photo</th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th class="text-center">Year</th>
                            <th class="text-center">Status</th>
                            <th>Files Data</th>
                            <th class="text-center pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($studentsList) > 0): ?>
                            <?php foreach ($studentsList as $student): ?>
                                <tr>
                                    <!-- 1. SEPARATE PROFILE PICTURE (Photo Avatar) -->
                                    <td class="ps-3 text-center">
                                        <?php if (!empty($student['profile_picture']) && record_file_exists($student['profile_picture'])): ?>
                                            <a href="download_file.php?file=<?= urlencode($student['profile_picture']); ?>&action=view" target="_blank" title="View Profile Photo">
                                                <img src="download_file.php?file=<?= urlencode($student['profile_picture']); ?>&action=view" 
                                                     alt="Profile" class="student-avatar">
                                            </a>
                                        <?php else: ?>
                                            <span class="avatar-initials" title="No profile picture">
                                                <?= strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="fw-bold text-primary">
                                        <?= htmlspecialchars($student['student_id']); ?>
                                    </td>

                                    <td class="fw-semibold">
                                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </td>

                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($student['email']); ?>" class="text-decoration-none text-muted">
                                            <?= htmlspecialchars($student['email']); ?>
                                        </a>
                                    </td>

                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($student['course']); ?>
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <?= htmlspecialchars($student['year_level']); ?>
                                    </td>

                                    <td class="text-center">
                                        <?php if ($student['status'] === 'Regular'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                Regular
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                                Irregular
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 2. SEPARATE FILES DATA (Documents / Records Only) -->
                                    <td>
                                        <?php if (!empty($student['record_file']) && record_file_exists($student['record_file'])): ?>
                                            <div class="d-flex align-items-center gap-1">
                                                <?= get_file_type_badge($student['record_file']); ?>
                                                <small class="text-muted ms-1"><?= get_record_file_size($student['record_file']); ?></small>
                                                <a href="download_file.php?file=<?= urlencode($student['record_file']); ?>&action=view" 
                                                   target="_blank" class="btn btn-sm btn-outline-primary py-0 px-1 ms-1" title="View Document">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="download_file.php?file=<?= urlencode($student['record_file']); ?>&action=download" 
                                                   class="btn btn-sm btn-outline-secondary py-0 px-1" title="Download Document">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">No file</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- ACTION BUTTONS: Edit and Delete are ADMIN ONLY -->
                                    <td class="text-center pe-3 action-btns">
                                        <!-- View Details & Files Modal (Accessible to all users) -->
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewModal<?= $student['id']; ?>"
                                                title="View Full Record & Files">
                                            <i class="bi bi-eye"></i> View
                                        </button>

                                        <?php if ($isAdmin): ?>
                                            <!-- Edit Button (ADMIN ONLY) -->
                                            <a href="edit_student.php?id=<?= $student['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="Edit Student Record">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </a>

                                            <!-- Delete Button (ADMIN ONLY) -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal<?= $student['id']; ?>"
                                                    title="Delete Student Record">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- View Details Modal for Student ID: <?= $student['student_id']; ?> -->
                                <div class="modal fade" id="viewModal<?= $student['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?= $student['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white" style="background: linear-gradient(135deg, #0d3b66 0%, #1e40af 100%) !important;">
                                                <h5 class="modal-title fw-bold" id="viewModalLabel<?= $student['id']; ?>">
                                                    <i class="bi bi-person-vcard-fill me-2"></i>Student Details
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <!-- Profile Picture Header -->
                                                <div class="text-center mb-3">
                                                    <?php if (!empty($student['profile_picture']) && record_file_exists($student['profile_picture'])): ?>
                                                        <img src="download_file.php?file=<?= urlencode($student['profile_picture']); ?>&action=view" 
                                                             alt="Profile Picture" class="avatar-modal-lg mb-2">
                                                        <div>
                                                            <a href="download_file.php?file=<?= urlencode($student['profile_picture']); ?>&action=view" target="_blank" class="small text-decoration-none">
                                                                <i class="bi bi-box-arrow-up-right me-1"></i>View full photo
                                                            </a>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="modal-initials-lg mb-2">
                                                            <?= strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                        </div>
                                                        <div class="small text-muted fst-italic">No profile picture</div>
                                                    <?php endif; ?>

                                                    <h5 class="fw-bold mt-2 mb-0"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h5>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-1">
                                                        ID: <?= htmlspecialchars($student['student_id']); ?>
                                                    </span>
                                                </div>

                                                <ul class="list-group list-group-flush border-top border-bottom mb-3">
                                                    <li class="list-group-item d-flex justify-content-between px-0">
                                                        <span class="text-muted">Email</span>
                                                        <span><?= htmlspecialchars($student['email']); ?></span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between px-0">
                                                        <span class="text-muted">Course</span>
                                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($student['course']); ?></span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between px-0">
                                                        <span class="text-muted">Year Level</span>
                                                        <span>Year <?= htmlspecialchars($student['year_level']); ?></span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between px-0">
                                                        <span class="text-muted">Status</span>
                                                        <span><?= htmlspecialchars($student['status']); ?></span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between px-0">
                                                        <span class="text-muted">Date Added</span>
                                                        <small><?= htmlspecialchars($student['created_at']); ?></small>
                                                    </li>
                                                </ul>

                                                <!-- Dedicated Files Data Box -->
                                                <div class="p-3 bg-light rounded border">
                                                    <div class="fw-bold text-dark small text-uppercase mb-2">
                                                        <i class="bi bi-file-earmark-arrow-up-fill text-success me-1"></i> Files Data (Attached Document)
                                                    </div>
                                                    <?php if (!empty($student['record_file']) && record_file_exists($student['record_file'])): ?>
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <div class="d-flex align-items-center">
                                                                <div class="me-2"><?= get_file_type_badge($student['record_file']); ?></div>
                                                                <div>
                                                                    <div class="small fw-bold text-dark text-break"><?= htmlspecialchars($student['record_file']); ?></div>
                                                                    <div class="small text-muted">Size: <?= get_record_file_size($student['record_file']); ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex gap-1">
                                                                <a href="download_file.php?file=<?= urlencode($student['record_file']); ?>&action=view" target="_blank" class="btn btn-sm btn-outline-primary" title="View Document">
                                                                    <i class="bi bi-eye"></i>
                                                                </a>
                                                                <a href="download_file.php?file=<?= urlencode($student['record_file']); ?>&action=download" class="btn btn-sm btn-outline-secondary" title="Download Document">
                                                                    <i class="bi bi-download"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-muted small fst-italic">No files data attached to this student record.</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <?php if ($isAdmin): ?>
                                                    <a href="edit_student.php?id=<?= $student['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-pencil-square me-1"></i> Edit This Record
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($isAdmin): ?>
                                    <!-- Delete Confirmation Modal (ADMIN ONLY) -->
                                    <div class="modal fade" id="deleteModal<?= $student['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $student['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title fw-bold" id="deleteModalLabel<?= $student['id']; ?>">
                                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Delete (Admin)
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" action="delete_student.php">
                                                    <div class="modal-body text-center py-4">
                                                        <div class="text-danger mb-3" style="font-size: 48px;">
                                                            <i class="bi bi-trash3"></i>
                                                        </div>
                                                        <h5 class="fw-bold mb-2">Are you sure you want to delete this student record?</h5>
                                                        <p class="text-muted mb-3">This action cannot be undone. All information will be removed.</p>
                                                        <div class="p-3 bg-light rounded text-start border small">
                                                            <div><strong>Student ID:</strong> <?= htmlspecialchars($student['student_id']); ?></div>
                                                            <div><strong>Full Name:</strong> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                            <div><strong>Course & Year:</strong> <?= htmlspecialchars($student['course'] . ' - Year ' . $student['year_level']); ?></div>
                                                            <?php if (!empty($student['profile_picture'])): ?>
                                                                <div class="text-danger mt-1">
                                                                    <i class="bi bi-person-x me-1"></i> Profile picture will also be deleted from disk.
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($student['record_file'])): ?>
                                                                <div class="text-danger mt-1">
                                                                    <i class="bi bi-file-earmark-x me-1"></i> Attached file (<?= htmlspecialchars($student['record_file']); ?>) will also be deleted from disk.
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <input type="hidden" name="id" value="<?= $student['id']; ?>">
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                            <i class="bi bi-x-circle me-1"></i> Cancel
                                                        </button>
                                                        <button type="submit" class="btn btn-danger fw-semibold">
                                                            <i class="bi bi-trash-fill me-1"></i> Yes, Delete
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="bi bi-folder-x fs-1 d-block mb-2 text-secondary"></i>
                                    <h5>No student records found.</h5>
                                    <p class="small mb-0">Try clearing your filters or check back later.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Student File Requests Section (Admin Review & Approval) -->
    <div class="card card-custom mt-4 mb-4" id="fileRequestsSection">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-inbox-fill text-warning me-2"></i>Student File Requests Management
                </h5>
                <span class="badge bg-secondary ms-2"><?= count($allRequests); ?> request(s)</span>
            </div>
            <div class="d-flex align-items-center gap-1 flex-wrap">
                <a href="dashboard.php#fileRequestsSection" class="btn btn-sm <?= empty($req_status_filter) ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    All (<?= $totalRequests; ?>)
                </a>
                <a href="dashboard.php?req_status=Pending#fileRequestsSection" class="btn btn-sm <?= ($req_status_filter === 'Pending') ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning text-dark'; ?>">
                    <i class="bi bi-hourglass-split me-1"></i>Pending (<?= $pendingRequests; ?>)
                </a>
                <a href="dashboard.php?req_status=Approved#fileRequestsSection" class="btn btn-sm <?= ($req_status_filter === 'Approved') ? 'btn-success fw-bold' : 'btn-outline-success'; ?>">
                    <i class="bi bi-check-circle-fill me-1"></i>Approved (<?= $approvedRequests; ?>)
                </a>
                <a href="dashboard.php?req_status=Rejected#fileRequestsSection" class="btn btn-sm <?= ($req_status_filter === 'Rejected') ? 'btn-danger fw-bold' : 'btn-outline-danger'; ?>">
                    <i class="bi bi-x-circle-fill me-1"></i>Rejected (<?= $rejectedRequests; ?>)
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-custom-header">
                        <tr>
                            <th class="ps-3 text-center" style="width: 75px;">Req #</th>
                            <th>Student</th>
                            <th>Document Requested</th>
                            <th>Purpose / Reason</th>
                            <th class="text-center">Attachment</th>
                            <th>Date Requested</th>
                            <th class="text-center">Status</th>
                            <th>Admin Remarks & File</th>
                            <th class="text-center pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($allRequests) > 0): ?>
                            <?php foreach ($allRequests as $req): ?>
                                <tr>
                                    <td class="ps-3 text-center fw-bold text-primary">#<?= $req['id']; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark">
                                            <?= htmlspecialchars(($req['first_name'] ?? 'Student') . ' ' . ($req['last_name'] ?? '')); ?>
                                        </div>
                                        <div class="small">
                                            <span class="text-primary fw-semibold"><?= htmlspecialchars($req['std_num'] ?? 'N/A'); ?></span>
                                            <span class="badge bg-light text-secondary border ms-1"><?= htmlspecialchars($req['course'] ?? ''); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">
                                            <i class="bi bi-file-earmark-text text-primary me-1"></i>
                                            <?= htmlspecialchars($req['document_type']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small text-muted text-break" style="max-width: 240px;">
                                            <?= htmlspecialchars($req['purpose'] ?: 'None specified'); ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($req['supporting_file']) && record_file_exists($req['supporting_file'])): ?>
                                            <a href="download_file.php?file=<?= urlencode($req['supporting_file']); ?>&action=view" target="_blank" class="btn btn-sm btn-outline-info py-0 px-2" title="View Attachment">
                                                <i class="bi bi-paperclip"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= date('M d, Y', strtotime($req['created_at'])); ?><br>
                                        <span class="text-secondary"><?= date('h:i A', strtotime($req['created_at'])); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?= get_request_status_badge($req['status']); ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($req['admin_notes'])): ?>
                                            <div class="small text-dark mb-1">
                                                <i class="bi bi-chat-quote-fill text-muted me-1"></i><?= htmlspecialchars($req['admin_notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($req['approved_file']) && record_file_exists($req['approved_file'])): ?>
                                            <div class="d-flex align-items-center gap-1">
                                                <?= get_file_type_badge($req['approved_file']); ?>
                                                <a href="download_file.php?file=<?= urlencode($req['approved_file']); ?>&action=view" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-1" title="View Document">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="download_file.php?file=<?= urlencode($req['approved_file']); ?>&action=download" class="btn btn-sm btn-outline-success py-0 px-1" title="Download Document">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                            </div>
                                        <?php elseif ($req['status'] === 'Approved'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Ready for Pickup</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-3 action-btns">
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveReqModal<?= $req['id']; ?>" title="Approve Request">
                                            <i class="bi bi-check-circle-fill"></i> Approve
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning text-dark" data-bs-toggle="modal" data-bs-target="#rejectReqModal<?= $req['id']; ?>" title="Reject Request">
                                            <i class="bi bi-x-circle-fill"></i> Reject
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteReqModal<?= $req['id']; ?>" title="Delete Record">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Modal: Approve Request -->
                                <div class="modal fade" id="approveReqModal<?= $req['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="bi bi-check-circle-fill me-2"></i>Approve File Request #<?= $req['id']; ?>
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="process_file_request.php" enctype="multipart/form-data">
                                                <input type="hidden" name="action" value="approve_request">
                                                <input type="hidden" name="request_id" value="<?= $req['id']; ?>">
                                                <div class="modal-body p-4 text-start">
                                                    <div class="p-3 bg-light rounded border mb-3">
                                                        <div><strong>Student:</strong> <?= htmlspecialchars(($req['first_name'] ?? '') . ' ' . ($req['last_name'] ?? '')); ?> (<?= htmlspecialchars($req['std_num'] ?? ''); ?>)</div>
                                                        <div><strong>Document:</strong> <?= htmlspecialchars($req['document_type']); ?></div>
                                                        <div><strong>Purpose:</strong> <?= htmlspecialchars($req['purpose'] ?: 'None'); ?></div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Attach Approved File / Document (Optional)</label>
                                                        <input type="file" class="form-control" name="approved_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip,.jpg,.png">
                                                        <div class="form-text">PDF, Word, or Scanned Document (Max 5MB). The student will be able to view and download it.</div>
                                                    </div>

                                                    <div class="mb-2">
                                                        <label class="form-label fw-semibold">Admin Remarks / Instructions for Student</label>
                                                        <textarea class="form-control" name="admin_notes" rows="2" placeholder="e.g. Approved. You may download your copy online or visit the registrar for the dry-sealed document."><?= htmlspecialchars($req['admin_notes'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-success fw-semibold">
                                                        <i class="bi bi-check-circle-fill me-1"></i> Confirm & Approve
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal: Reject Request -->
                                <div class="modal fade" id="rejectReqModal<?= $req['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header bg-warning text-dark">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="bi bi-x-circle-fill me-2"></i>Reject File Request #<?= $req['id']; ?>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="process_file_request.php">
                                                <input type="hidden" name="action" value="reject_request">
                                                <input type="hidden" name="request_id" value="<?= $req['id']; ?>">
                                                <div class="modal-body p-4 text-start">
                                                    <div class="p-3 bg-light rounded border mb-3">
                                                        <div><strong>Student:</strong> <?= htmlspecialchars(($req['first_name'] ?? '') . ' ' . ($req['last_name'] ?? '')); ?> (<?= htmlspecialchars($req['std_num'] ?? ''); ?>)</div>
                                                        <div><strong>Document:</strong> <?= htmlspecialchars($req['document_type']); ?></div>
                                                    </div>

                                                    <div class="mb-2">
                                                        <label class="form-label fw-semibold">Reason for Rejection <span class="text-danger">*</span></label>
                                                        <textarea class="form-control" name="admin_notes" rows="3" placeholder="e.g. Incomplete clearance requirements. Please submit your clearance slip first." required><?= htmlspecialchars($req['admin_notes'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger fw-semibold">
                                                        <i class="bi bi-x-circle-fill me-1"></i> Confirm Rejection
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal: Delete Request Record -->
                                <div class="modal fade" id="deleteReqModal<?= $req['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="bi bi-trash3-fill me-2"></i>Delete File Request #<?= $req['id']; ?>
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="process_file_request.php">
                                                <input type="hidden" name="action" value="delete_request">
                                                <input type="hidden" name="request_id" value="<?= $req['id']; ?>">
                                                <div class="modal-body text-center py-4">
                                                    <div class="text-danger mb-3" style="font-size: 40px;">
                                                        <i class="bi bi-trash3"></i>
                                                    </div>
                                                    <h5 class="fw-bold mb-2">Delete this file request record?</h5>
                                                    <p class="text-muted small mb-0">This will remove the request and any attached files permanently.</p>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger fw-semibold">Yes, Delete</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 text-secondary d-block mb-2"></i>
                                    <h5>No student file requests found.</h5>
                                    <p class="small mb-0">When students submit document requests from their dashboard, they will appear here.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
    <!-- Add Student Modal (ADMIN ONLY) -->
    <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white" style="background: linear-gradient(135deg, #1e40af 0%, #ea580c 100%) !important;">
                    <h5 class="modal-title fw-bold" id="addStudentModalLabel">
                        <i class="bi bi-shield-plus me-2"></i>Add New Student Record (Admin)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="add_student.php" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Student ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="student_id" placeholder="e.g. 2026006" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Course / Program <span class="text-danger">*</span></label>
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
                                <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="first_name" placeholder="e.g. John" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="last_name" placeholder="e.g. Doe" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" placeholder="e.g. john.doe@example.com" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Year Level <span class="text-danger">*</span></label>
                                <select class="form-select" name="year_level" required>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Enrollment Status <span class="text-danger">*</span></label>
                                <select class="form-select" name="status" required>
                                    <option value="Regular" selected>Regular</option>
                                    <option value="Irregular">Irregular</option>
                                </select>
                            </div>

                            <!-- 1. SEPARATE PROFILE PICTURE INPUT -->
                            <div class="col-md-12 mt-3 pt-3 border-top">
                                <div class="p-3 bg-light rounded border">
                                    <label class="form-label fw-semibold d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-primary">
                                            <i class="bi bi-person-bounding-box me-1"></i> Profile Picture (Photo Only)
                                        </span>
                                        <span class="badge bg-white text-secondary border">Optional</span>
                                    </label>
                                    <div class="row align-items-center g-2 mt-1">
                                        <div class="col-auto" id="modalPicThumbCol" style="display: none;">
                                            <img id="modalPicThumb" src="#" alt="Preview" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #ea580c;">
                                        </div>
                                        <div class="col">
                                            <input type="file" class="form-control" name="profile_picture" id="modal_profile_pic"
                                                   accept=".jpg,.jpeg,.png,.webp,.gif"
                                                   onchange="previewModalPic(this)">
                                            <div class="form-text">JPG, PNG, WEBP, GIF (Max 3MB). Used as avatar.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. SEPARATE FILES DATA INPUT -->
                            <div class="col-md-12 mt-2">
                                <div class="p-3 bg-light rounded border">
                                    <label class="form-label fw-semibold d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-success">
                                            <i class="bi bi-file-earmark-arrow-up-fill me-1"></i> Files Data (Documents / Data Only)
                                        </span>
                                        <span class="badge bg-white text-secondary border">Optional</span>
                                    </label>
                                    <input type="file" class="form-control mt-1" name="record_file" id="modal_record_file"
                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip"
                                           onchange="previewModalDoc(this)">
                                    <div class="form-text">PDF, DOC, DOCX, XLS, XLSX, CSV, TXT, ZIP (Max 5MB).</div>

                                    <div id="modalDocPreviewBox" class="mt-2 p-2 bg-white rounded border d-none">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-check-fill text-success fs-4 me-2"></i>
                                                <div class="small">
                                                    <strong id="modalDocName" class="text-dark"></strong>
                                                    <span id="modalDocSize" class="text-muted ms-1"></span>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearModalDocInput()">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-orange fw-semibold">
                            <i class="bi bi-plus-circle me-1"></i> Save Student Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewModalPic(input) {
    const file = input.files[0];
    const thumbCol = document.getElementById('modalPicThumbCol');
    const thumbImg = document.getElementById('modalPicThumb');

    if (file) {
        if (file.size > 3 * 1024 * 1024) {
            alert("Profile picture exceeds the maximum allowed limit of 3MB.");
            input.value = "";
            thumbCol.style.display = "none";
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            thumbImg.src = e.target.result;
            thumbCol.style.display = "block";
        };
        reader.readAsDataURL(file);
    } else {
        thumbCol.style.display = "none";
    }
}

function previewModalDoc(input) {
    const file = input.files[0];
    const previewBox = document.getElementById('modalDocPreviewBox');
    const nameLabel = document.getElementById('modalDocName');
    const sizeLabel = document.getElementById('modalDocSize');

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

function clearModalDocInput() {
    const input = document.getElementById('modal_record_file');
    input.value = '';
    document.getElementById('modalDocPreviewBox').classList.add('d-none');
}
</script>
</body>
</html>

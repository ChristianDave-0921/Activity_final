<?php
// register.php - Student Registration with Automatic Academic Profile Creation
session_start();
require_once 'db_connection.php';

// If already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: dashboard.php");
    } else {
        header("Location: student_dashboard.php");
    }
    exit();
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name  = trim($_POST['full_name'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $course     = trim($_POST['course'] ?? 'BSIT');
    $year_level = intval($_POST['year_level'] ?? 1);
    $student_id = trim($_POST['student_id'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $error = "All required fields must be filled out.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if username already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $error = "Username '$username' is already taken. Please choose another.";
            $checkStmt->close();
        } else {
            $checkStmt->close();

            // Hash password and insert into users
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, 'user')");
            $insertStmt->bind_param("ssss", $username, $hashedPassword, $full_name, $email);

            if ($insertStmt->execute()) {
                $newUserId = $insertStmt->insert_id;
                $insertStmt->close();

                // Generate Student ID if not provided
                if (empty($student_id)) {
                    $student_id = '2026' . sprintf('%03d', $newUserId);
                }

                // Check Student ID uniqueness in students table
                $checkSid = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
                $checkSid->bind_param("s", $student_id);
                $checkSid->execute();
                $checkSid->store_result();
                if ($checkSid->num_rows > 0) {
                    $student_id = '2026' . sprintf('%03d', $newUserId) . rand(10, 99);
                }
                $checkSid->close();

                // Split name
                $nameParts = explode(' ', $full_name);
                $first = $nameParts[0];
                $last  = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : 'Student';

                // Insert into students table linked with user_id
                $stdStmt = $conn->prepare("INSERT INTO students (user_id, student_id, first_name, last_name, email, course, year_level, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Regular')");
                $stdStmt->bind_param("isssssi", $newUserId, $student_id, $first, $last, $email, $course, $year_level);
                $stdStmt->execute();
                $stdStmt->close();

                $_SESSION['success'] = "Registration successful! You can now sign in to your student dashboard.";
                header("Location: login.php");
                exit();
            } else {
                $error = "Registration failed: " . $conn->error;
                $insertStmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Student Request Records System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            /* Blue and Orange Gradient Background */
            background: linear-gradient(135deg, #0d3b66 0%, #1e40af 45%, #ea580c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .register-card {
            width: 100%;
            max-width: 520px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: fadeIn 0.4s ease-in-out;
            border-top: 4px solid #ea580c;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .register-header {
            background: linear-gradient(135deg, #1e40af 0%, #ea580c 100%);
            color: #ffffff;
            padding: 25px 20px 20px;
            text-align: center;
        }
        .register-header .icon-circle {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .register-body {
            padding: 30px;
        }
        .form-floating input:focus, .form-floating select:focus {
            border-color: #ea580c;
            box-shadow: 0 0 0 0.25rem rgba(234, 88, 12, 0.2);
        }
        .btn-register {
            background: linear-gradient(135deg, #1d4ed8 0%, #ea580c 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            color: #ffffff;
            transition: all 0.2s ease-in-out;
        }
        .btn-register:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="register-card">
    <div class="register-header">
        <div class="icon-circle">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <h4 class="mb-1 fw-bold">Student Registration</h4>
        <p class="mb-0 text-white-50 fs-6">Student Request Records System</p>
    </div>

    <div class="register-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($error); ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="full_name" name="full_name" placeholder="Full Name" value="<?= htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                <label for="full_name"><i class="bi bi-card-heading me-1"></i> Full Name</label>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <div class="form-floating">
                        <select class="form-select" id="course" name="course" required>
                            <option value="BSIT" selected>BSIT</option>
                            <option value="BSCS">BSCS</option>
                            <option value="BSIS">BSIS</option>
                            <option value="BSEMC">BSEMC</option>
                            <option value="BSBA">BSBA</option>
                            <option value="BSCPE">BSCPE</option>
                        </select>
                        <label for="course"><i class="bi bi-journal-code me-1"></i> Course</label>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-floating">
                        <select class="form-select" id="year_level" name="year_level" required>
                            <option value="1" selected>Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                        </select>
                        <label for="year_level"><i class="bi bi-calendar-event me-1"></i> Year Level</label>
                    </div>
                </div>
            </div>

            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="username" name="username" placeholder="Username" value="<?= htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                <label for="username"><i class="bi bi-person me-1"></i> Portal Username</label>
            </div>

            <div class="form-floating mb-3">
                <input type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                <label for="email"><i class="bi bi-envelope me-1"></i> Email Address</label>
            </div>

            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" minlength="6" required>
                <label for="password"><i class="bi bi-lock me-1"></i> Password (min. 6 characters)</label>
            </div>

            <div class="form-floating mb-4">
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" minlength="6" required>
                <label for="confirm_password"><i class="bi bi-shield-check me-1"></i> Confirm Password</label>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-register mb-3">
                <i class="bi bi-person-check me-1"></i> Register Student Account
            </button>
        </form>

        <div class="text-center text-muted small">
            Already have an account? <a href="login.php" class="text-decoration-none fw-semibold">Sign in here</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// login.php - User Login & Authentication
session_start();
require_once 'db_connection.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Please fill in all required fields.";
    } else {
        // Prepared statement for secure authentication
        $sql = "SELECT id, username, password, full_name, role FROM users WHERE username = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // Password matches
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role']      = $user['role'] ?? ($user['username'] === 'admin' ? 'admin' : 'user');
                    $_SESSION['success']   = "Welcome back, " . htmlspecialchars($user['full_name']) . "!";
                    
                    if ($_SESSION['role'] === 'admin' || strtolower($user['username']) === 'admin') {
                        header("Location: dashboard.php");
                    } else {
                        header("Location: student_dashboard.php");
                    }
                    exit();
                } else {
                    $error = "Invalid username or password.";
                }
            } else {
                $error = "Invalid username or password.";
            }
            $stmt->close();
        } else {
            $error = "Database query error: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student Request Records System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0d3b66 0%, #1e40af 45%, #ea580c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 440px;
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
        .login-header {
            background: linear-gradient(135deg, #1e40af 0%, #ea580c 100%);
            color: #ffffff;
            padding: 30px 20px 25px;
            text-align: center;
        }
        .login-header .icon-circle {
            width: 65px;
            height: 65px;
            background: rgba(255, 255, 255, 0.2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 32px;
            margin-bottom: 12px;
        }
        .login-body {
            padding: 30px;
        }
        .form-floating input:focus {
            border-color: #ea580c;
            box-shadow: 0 0 0 0.25rem rgba(234, 88, 12, 0.2);
        }
        .btn-login {
            background: linear-gradient(135deg, #1d4ed8 0%, #ea580c 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.2s ease-in-out;
        }
        .btn-login:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }
        .demo-box {
            background-color: #f8f9fa;
            border: 1px dashed #ced4da;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.85rem;
            color: #495057;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="icon-circle">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <h4 class="mb-1 fw-bold">Student Request Records System</h4>
        <p class="mb-0 text-white-50 fs-6">Sign in to request and manage student records</p>
    </div>

    <div class="login-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($error); ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-shield-lock-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($_SESSION['error']); ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($_SESSION['success']); ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required autofocus>
                <label for="username"><i class="bi bi-person me-1"></i> Username</label>
            </div>

            <div class="form-floating mb-4">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                <label for="password"><i class="bi bi-lock me-1"></i> Password</label>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-login mb-3">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>
        </form>

        <div class="demo-box mb-3 text-center">
            <i class="bi bi-info-circle text-primary me-1"></i>
            <strong>Default Admin Credentials:</strong><br>
            Username: <code>admin</code> &bull; Password: <code>password123</code>
        </div>

        <div class="text-center text-muted small">
            Don't have an account? <a href="register.php" class="text-decoration-none fw-semibold">Register here</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

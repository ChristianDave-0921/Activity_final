<?php
// index.php - Smart Entry Point Routing
session_start();

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    require_once 'auth_check.php';
    if (is_admin()) {
        header("Location: dashboard.php");
    } else {
        header("Location: student_dashboard.php");
    }
    exit();
} else {
    header("Location: login.php");
    exit();
}

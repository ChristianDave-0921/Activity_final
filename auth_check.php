<?php
// auth_check.php - Session Authentication Guard & Role Authorization Helpers
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated users to login page
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $_SESSION['error'] = "Access denied. Please log in first.";
    header("Location: login.php");
    exit();
}

/**
 * Check if the currently logged-in user is an administrator
 *
 * @return bool
 */
function is_admin(): bool {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') {
        return true;
    }
    if (isset($_SESSION['username']) && strtolower($_SESSION['username']) === 'admin') {
        return true;
    }
    return false;
}

/**
 * Enforce administrator access only
 * Redirects non-admin users back to dashboard with a flash message
 */
function require_admin(): void {
    if (!is_admin()) {
        $_SESSION['error'] = "Access denied: Administrator privileges are required to add, edit, or delete student records.";
        header("Location: dashboard.php");
        exit();
    }
}

<?php
// logout.php - Secure User Logout
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start fresh session just for the flash message
session_start();
$_SESSION['success'] = "You have been logged out successfully.";

header("Location: login.php");
exit();
?>

<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../views/auth/login.php');
    exit();
}

// Check if user is admin (for admin pages)
$is_admin_page = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
if ($is_admin_page && (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1)) {
    header('Location: ../user/dashboard.php');
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Check for inactivity (30 minutes timeout)
$inactive_time = 30 * 60; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive_time)) {
    session_unset();
    session_destroy();
    header('Location: ../views/auth/login.php?timeout=1');
    exit();
}
?>
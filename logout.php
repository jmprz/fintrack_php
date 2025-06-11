<?php
session_start();
require_once 'connection.php';

// Update logout time in login_activities if there's an active session
if (isset($_SESSION['login_activity_id'])) {
    $stmt = $con->prepare("UPDATE login_activities SET logout_time = CURRENT_TIMESTAMP WHERE activity_id = ?");
    $stmt->bind_param("i", $_SESSION['login_activity_id']);
    $stmt->execute();
    $stmt->close();
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Clear the remember me cookie
if (isset($_COOKIE['user_id'])) {
    setcookie('user_id', '', time() - 3600, '/'); // Set the cookie expiration date to the past
}

// Redirect to login page
header("Location: login.php");
exit;
?>
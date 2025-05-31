<?php
session_start();

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
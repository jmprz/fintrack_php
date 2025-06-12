<?php
error_reporting(0);
ini_set('display_errors', 0);

$dbhost = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "fintrack";

$con = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (!$con) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // This is an AJAX request, return JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit();
    } else {
        // Regular request, show simple message
        die("Database connection failed");
    }
}
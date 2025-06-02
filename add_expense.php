<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Validate input
if (!isset($_POST['date']) || !isset($_POST['particulars']) || !isset($_POST['category']) || !isset($_POST['amount'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$user_id = $_SESSION['user_id'];
$date = $_POST['date'];
$particulars = $_POST['particulars'];
$category = $_POST['category'];
$amount = floatval($_POST['amount']);

// Insert the expense
$stmt = $con->prepare("INSERT INTO expenses (user_id, date, particulars, category, amount) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("isssd", $user_id, $date, $particulars, $category, $amount);

$success = $stmt->execute();

header('Content-Type: application/json');
echo json_encode(['success' => $success]);

$stmt->close();
$con->close();
?> 
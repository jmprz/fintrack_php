<?php
session_start();
require_once '../connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$company_id = intval($_POST['company_id']);
$user_id = $_SESSION['user_id'];

// Verify user has access to this company
$verify_stmt = $con->prepare("
    SELECT 1 FROM user_companies 
    WHERE user_id = ? AND company_id = ?
");
$verify_stmt->bind_param("ii", $user_id, $company_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Company not found or access denied']);
    $verify_stmt->close();
    $con->close();
    exit();
}
$verify_stmt->close();

// Add new account title
$stmt = $con->prepare("
    INSERT INTO account_titles (company_id, title_name, type)
    VALUES (?, ?, ?)
");

$title_name = trim($_POST['title_name']);
$type = $_POST['type'];

$stmt->bind_param("iss", $company_id, $title_name, $type);
$success = $stmt->execute();

header('Content-Type: application/json');
if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error adding category']);
}

$stmt->close();
$con->close();
?> 
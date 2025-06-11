<?php
session_start();
require_once '../connection.php';

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
$company_id = $_SESSION['selected_company_id'] ?? null;

// Verify user has access to this company
if (!$company_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No company selected']);
    exit();
}

$verify_stmt = $con->prepare("SELECT 1 FROM user_companies WHERE user_id = ? AND company_id = ? LIMIT 1");
$verify_stmt->bind_param("ii", $user_id, $company_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Company access denied']);
    exit();
}
$verify_stmt->close();

// Insert the expense
$stmt = $con->prepare("INSERT INTO expenses (user_id, company_id, date, particulars, category, amount) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iisssd", $user_id, $company_id, $date, $particulars, $category, $amount);

$success = $stmt->execute();

header('Content-Type: application/json');
echo json_encode(['success' => $success]);

$stmt->close();
$con->close();
?> 
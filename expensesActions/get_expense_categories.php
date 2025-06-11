<?php
session_start();
require_once '../connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$company_id = isset($_SESSION['selected_company_id']) ? $_SESSION['selected_company_id'] : null;

if (!$company_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No company selected']);
    exit();
}

// Verify user has access to this company
$verify_stmt = $con->prepare("
    SELECT 1 FROM user_companies 
    WHERE user_id = ? AND company_id = ?
");
$verify_stmt->bind_param("ii", $_SESSION['user_id'], $company_id);
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

// Get expense categories for the company
$stmt = $con->prepare("
    SELECT title_name 
    FROM account_titles 
    WHERE company_id = ? AND type = 'expense'
    ORDER BY title_name
");

$stmt->bind_param("i", $company_id);
$stmt->execute();
$result = $stmt->get_result();

$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = $row['title_name'];
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $categories]);

$stmt->close();
$con->close();
?> 
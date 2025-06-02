<?php
session_start();
require_once 'connection.php';

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

// Get sale ID from URL
$sale_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$sale_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid sale ID']);
    exit();
}

// Verify sale belongs to user's company
$verify_stmt = $con->prepare("
    SELECT 1 FROM sales 
    WHERE sale_id = ? AND company_id = ?
");
$verify_stmt->bind_param("ii", $sale_id, $company_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Sale not found or access denied']);
    $verify_stmt->close();
    $con->close();
    exit();
}
$verify_stmt->close();

// Delete the sale
$stmt = $con->prepare("DELETE FROM sales WHERE sale_id = ? AND company_id = ?");
$stmt->bind_param("ii", $sale_id, $company_id);
$success = $stmt->execute();

header('Content-Type: application/json');
echo json_encode(['success' => $success]);

$stmt->close();
$con->close();
?> 
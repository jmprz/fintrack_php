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

// Get sale details
$stmt = $con->prepare("
    SELECT s.*, at.title_name as category
    FROM sales s
    JOIN account_titles at ON s.title_id = at.title_id
    WHERE s.sale_id = ? AND s.company_id = ?
");

$stmt->bind_param("ii", $sale_id, $company_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Sale not found or access denied']);
    $stmt->close();
    $con->close();
    exit();
}

$sale = $result->fetch_assoc();
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'date' => $sale['date'],
    'particulars' => $sale['particulars'],
    'category' => $sale['category'],
    'amount' => $sale['amount']
]);

$stmt->close();
$con->close();
?> 
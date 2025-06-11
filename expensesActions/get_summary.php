<?php
session_start();
require_once '../connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get parameters
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$category_search = isset($_GET['category_search']) ? $_GET['category_search'] : '';
$company_id = isset($_SESSION['selected_company_id']) ? $_SESSION['selected_company_id'] : null;

if (!$company_id) {
    header('Content-Type: application/json');
    echo json_encode([
        'total' => 0,
        'total_without_loan' => 0
    ]);
    exit();
}

// Build the base query
$query = "SELECT 
    SUM(amount) as total,
    SUM(CASE WHEN category NOT IN ('LOAN AMORTIZATION', 'UNIFORM') THEN amount ELSE 0 END) as total_without_loan
FROM expenses 
WHERE MONTH(date) = ? AND YEAR(date) = ? AND company_id = ?";

$params = [$month, $year, $company_id];
$types = "iii";

// Add category search if provided
if (!empty($category_search)) {
    $query .= " AND category LIKE ?";
    $params[] = "%$category_search%";
    $types .= "s";
}

$stmt = $con->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'total' => $data['total'] ?? 0,
    'total_without_loan' => $data['total_without_loan'] ?? 0
]);

$stmt->close();
$con->close();
?> 
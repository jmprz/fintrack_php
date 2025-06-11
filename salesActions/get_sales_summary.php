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

// Get parameters
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$category_search = isset($_GET['category_search']) ? $_GET['category_search'] : '';

// Build base query for total sales
$query = "
    SELECT SUM(s.amount) as total
    FROM sales s
    JOIN account_titles at ON s.title_id = at.title_id
    WHERE s.company_id = ? 
    AND MONTH(s.date) = ? 
    AND YEAR(s.date) = ?
";

if ($category_search) {
    $query .= " AND at.title_name LIKE ?";
}

// Get total sales
$stmt = $con->prepare($query);
if ($category_search) {
    $search_param = "%$category_search%";
    $stmt->bind_param("iiis", $company_id, $month, $year, $search_param);
} else {
    $stmt->bind_param("iii", $company_id, $month, $year);
}

$stmt->execute();
$result = $stmt->get_result();
$total = $result->fetch_assoc()['total'] ?? 0;

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'total' => $total
]);

$stmt->close();
$con->close();
?> 
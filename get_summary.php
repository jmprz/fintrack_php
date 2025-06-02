<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$category_search = isset($_GET['category_search']) ? $_GET['category_search'] : '';

// Get total expenses (all)
$total_query = "SELECT COALESCE(SUM(amount), 0) as total_amount
                FROM expenses 
                WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";
$params = [$user_id, $month, $year];
$types = "iii";

if (!empty($category_search)) {
    $total_query .= " AND category LIKE ?";
    $params[] = "%$category_search%";
    $types .= "s";
}

$stmt = $con->prepare($total_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$total = $result->fetch_assoc()['total_amount'];
$stmt->close();

// Get total expenses without loan and uniform
$filtered_query = "SELECT COALESCE(SUM(amount), 0) as total_amount
                  FROM expenses 
                  WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ? 
                  AND category NOT IN ('LOAN AMORTIZATION', 'UNIFORM')";
$params = [$user_id, $month, $year];
$types = "iii";

if (!empty($category_search)) {
    $filtered_query .= " AND category LIKE ?";
    $params[] = "%$category_search%";
    $types .= "s";
}

$stmt = $con->prepare($filtered_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$total_filtered = $result->fetch_assoc()['total_amount'];
$stmt->close();

// Prepare response
$response = [
    'total' => floatval($total),
    'total_without_loan' => floatval($total_filtered)
];

header('Content-Type: application/json');
echo json_encode($response);

$con->close();
?> 
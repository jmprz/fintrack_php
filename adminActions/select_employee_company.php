<?php
session_start();
require_once '../connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get employee ID from request
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;

if (!$employee_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
    exit();
}

// Get companies assigned to the employee
$companies_stmt = $con->prepare("
    SELECT c.company_id, c.company_name 
    FROM companies c
    JOIN user_companies uc ON c.company_id = uc.company_id
    WHERE uc.user_id = ?
    ORDER BY c.company_name
");
$companies_stmt->bind_param("i", $employee_id);
$companies_stmt->execute();
$result = $companies_stmt->get_result();

$companies = [];
while ($row = $result->fetch_assoc()) {
    $companies[] = $row;
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'companies' => $companies]);
?> 
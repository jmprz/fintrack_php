<?php
session_start();
require_once '../connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$employee_id = isset($data['employee_id']) ? intval($data['employee_id']) : 0;
$company_id = isset($data['company_id']) ? intval($data['company_id']) : 0;

if (!$employee_id || !$company_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Verify the employee has access to this company
$verify_stmt = $con->prepare("
    SELECT 1 
    FROM user_companies 
    WHERE user_id = ? AND company_id = ?
    LIMIT 1
");
$verify_stmt->bind_param("ii", $employee_id, $company_id);
$verify_stmt->execute();
$result = $verify_stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Employee does not have access to this company']);
    exit();
}

// Store the selected company ID in session
$_SESSION['viewing_company_id'] = $company_id;
$_SESSION['viewing_employee_id'] = $employee_id;

header('Content-Type: application/json');
echo json_encode(['success' => true]);
?> 
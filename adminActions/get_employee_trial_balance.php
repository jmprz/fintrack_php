<?php
session_start();
require_once '../connection.php';

// Debug logging
error_log("Session variables: " . print_r($_SESSION, true));
error_log("POST data: " . print_r($_POST, true));

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['account_type'] !== 'Admin') {
    error_log("Authentication failed: user_id=" . isset($_SESSION['user_id']) . ", is_admin=" . isset($_SESSION['is_admin']) . ", account_type=" . ($_SESSION['account_type'] ?? 'not set'));
    die(json_encode(['error' => 'Not authenticated']));
}

// Check if viewing employee and company are set
if (!isset($_SESSION['viewing_employee_id']) || !isset($_SESSION['viewing_company_id'])) {
    error_log("Missing required session variables: viewing_employee_id=" . isset($_SESSION['viewing_employee_id']) . ", viewing_company_id=" . isset($_SESSION['viewing_company_id']));
    die(json_encode(['error' => 'No employee or company selected']));
}

$company_id = $_SESSION['viewing_company_id'];
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

error_log("Querying trial balance for company_id=$company_id and year=$year");

try {
    // Get trial balance data
    $query = "SELECT 
                id,
                classification,
                category,
                account_code_sap,
                description,
                ending_balance
              FROM trial_balance 
              WHERE company_id = ? AND year = ?
              ORDER BY classification ASC, category ASC";

    $stmt = $con->prepare($query);
    $stmt->bind_param("ii", $company_id, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    error_log("Found " . count($data) . " records");
    
    echo json_encode([
        'data' => $data
    ]);
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$stmt->close();
$con->close();
?> 
<?php
session_start();
require_once '../connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

// Check if company is selected
if (!isset($_SESSION['selected_company_id'])) {
    die(json_encode(['success' => false, 'message' => 'No company selected']));
}

// Validate required fields
if (!isset($_POST['id']) || !isset($_POST['classification']) || !isset($_POST['account_code_sap']) || 
    !isset($_POST['description']) || !isset($_POST['ending_balance'])) {
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

$id = intval($_POST['id']);
$company_id = $_SESSION['selected_company_id'];
$classification = $_POST['classification'];
$category = isset($_POST['category']) ? $_POST['category'] : null;
$account_code_sap = $_POST['account_code_sap'];
$description = $_POST['description'];
$ending_balance = floatval($_POST['ending_balance']);

try {
    // First verify the entry belongs to the current company
    $verify_query = "SELECT id FROM trial_balance WHERE id = ? AND company_id = ?";
    $verify_stmt = $con->prepare($verify_query);
    $verify_stmt->bind_param("ii", $id, $company_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        die(json_encode(['success' => false, 'message' => 'Entry not found or access denied']));
    }
    
    $query = "UPDATE trial_balance 
              SET classification = ?, category = ?, account_code_sap = ?, description = ?, ending_balance = ? 
              WHERE id = ? AND company_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("ssssdii", $classification, $category, $account_code_sap, $description, $ending_balance, $id, $company_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Entry updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update entry']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$verify_stmt->close();
$stmt->close();
$con->close();
?> 
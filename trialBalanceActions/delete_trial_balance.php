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

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    die(json_encode(['success' => false, 'message' => 'No entry ID provided']));
}

$id = intval($data['id']);
$company_id = $_SESSION['selected_company_id'];

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
    
    $query = "DELETE FROM trial_balance WHERE id = ? AND company_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("ii", $id, $company_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Entry deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete entry']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$verify_stmt->close();
$stmt->close();
$con->close();
?> 
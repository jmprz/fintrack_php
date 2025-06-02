<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$company_id = intval($_POST['company_id']);
$user_id = $_SESSION['user_id'];

// Start transaction
$con->begin_transaction();

try {
    // Verify user has access to this company
    $verify_stmt = $con->prepare("
        SELECT 1 FROM user_companies 
        WHERE user_id = ? AND company_id = ?
    ");
    $verify_stmt->bind_param("ii", $user_id, $company_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {
        throw new Exception('Company not found or access denied');
    }
    $verify_stmt->close();

    // Delete related records first
    // Delete from user_companies
    $stmt = $con->prepare("DELETE FROM user_companies WHERE company_id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $stmt->close();

    // Delete from expenses
    $stmt = $con->prepare("DELETE FROM expenses WHERE company_id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $stmt->close();

    // Delete from sales
    $stmt = $con->prepare("DELETE FROM sales WHERE company_id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $stmt->close();

    // Finally, delete the company
    $stmt = $con->prepare("DELETE FROM companies WHERE company_id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $stmt->close();

    // If this was the selected company, clear the session variables
    if (isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] == $company_id) {
        unset($_SESSION['selected_company_id']);
        unset($_SESSION['selected_company_name']);
    }

    $con->commit();
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $con->rollback();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$con->close();
?> 
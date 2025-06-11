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

// Get sale ID from URL
$sale_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$sale_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid sale ID']);
    exit();
}

// Start transaction
$con->begin_transaction();

try {
    // First, get the sale data before deletion
    $data_stmt = $con->prepare("
        SELECT s.*, at.title_name as category
        FROM sales s
        JOIN account_titles at ON s.title_id = at.title_id
        WHERE s.sale_id = ? AND s.company_id = ?
    ");
    $data_stmt->bind_param("ii", $sale_id, $company_id);
    $data_stmt->execute();
    $data_result = $data_stmt->get_result();
    
    if ($data_result->num_rows === 0) {
        throw new Exception('Sale not found or access denied');
    }
    
    $sale_data = $data_result->fetch_assoc();
    $data_stmt->close();

    // Delete the sale
    $delete_stmt = $con->prepare("DELETE FROM sales WHERE sale_id = ? AND company_id = ?");
    $delete_stmt->bind_param("ii", $sale_id, $company_id);
    $success = $delete_stmt->execute();
    $delete_stmt->close();

    if ($success) {
        // Record in work history
        $details = json_encode([
            'sale_id' => $sale_id,
            'deleted_data' => [
                'date' => $sale_data['date'],
                'particulars' => $sale_data['particulars'],
                'category' => $sale_data['category'],
                'amount' => $sale_data['amount']
            ]
        ]);

        $history_stmt = $con->prepare("INSERT INTO user_work_history (user_id, action_type, details) VALUES (?, 'sale_deleted', ?)");
        $history_stmt->bind_param("is", $_SESSION['user_id'], $details);
        $history_stmt->execute();
        $history_stmt->close();

        $con->commit();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Sale deleted successfully']);
    } else {
        throw new Exception("Failed to delete sale");
    }
} catch (Exception $e) {
    $con->rollback();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$con->close();
?> 
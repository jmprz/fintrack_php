<?php
session_start();
require_once '../connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Validate input
if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing expense ID']);
    exit();
}

$user_id = $_SESSION['user_id'];
$expense_id = intval($_GET['id']);
$company_id = $_SESSION['selected_company_id'] ?? null;

// Verify user has access to this company
if (!$company_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No company selected']);
    exit();
}

$verify_stmt = $con->prepare("SELECT 1 FROM user_companies WHERE user_id = ? AND company_id = ? LIMIT 1");
$verify_stmt->bind_param("ii", $user_id, $company_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Company access denied']);
    exit();
}
$verify_stmt->close();

// Start transaction
$con->begin_transaction();

try {
    // First, get the expense data before deletion
    $data_stmt = $con->prepare("SELECT * FROM expenses WHERE expense_id = ? AND user_id = ? AND company_id = ? LIMIT 1");
    $data_stmt->bind_param("iii", $expense_id, $user_id, $company_id);
    $data_stmt->execute();
    $data_result = $data_stmt->get_result();
    
    if ($data_result->num_rows === 0) {
        throw new Exception('Expense not found or access denied');
    }
    
    $expense_data = $data_result->fetch_assoc();
    $data_stmt->close();

    // Delete the expense
    $delete_stmt = $con->prepare("DELETE FROM expenses WHERE expense_id = ? AND user_id = ? AND company_id = ?");
    $delete_stmt->bind_param("iii", $expense_id, $user_id, $company_id);
    $success = $delete_stmt->execute();
    $delete_stmt->close();

    if ($success) {
        // Record in work history
        $details = json_encode([
            'expense_id' => $expense_id,
            'deleted_data' => [
                'date' => $expense_data['date'],
                'particulars' => $expense_data['particulars'],
                'category' => $expense_data['category'],
                'amount' => $expense_data['amount']
            ]
        ]);

        $history_stmt = $con->prepare("INSERT INTO user_work_history (user_id, action_type, details) VALUES (?, 'expense_deleted', ?)");
        $history_stmt->bind_param("is", $user_id, $details);
        $history_stmt->execute();
        $history_stmt->close();

        $con->commit();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Expense deleted successfully']);
    } else {
        throw new Exception("Failed to delete expense");
    }
} catch (Exception $e) {
    $con->rollback();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$con->close();
?> 
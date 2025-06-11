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
if (!isset($_GET['id']) || !isset($_POST['date']) || !isset($_POST['particulars']) || !isset($_POST['category']) || !isset($_POST['amount'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$user_id = $_SESSION['user_id'];
$expense_id = intval($_GET['id']);
$date = $_POST['date'];
$particulars = $_POST['particulars'];
$category = $_POST['category'];
$amount = floatval($_POST['amount']);
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
    // First, get the old expense data
    $old_data_stmt = $con->prepare("
        SELECT 
            e.*,
            at.title_name as category
        FROM expenses e
        JOIN account_titles at ON e.account_title_id = at.title_id
        WHERE e.expense_id = ? AND e.user_id = ? AND e.company_id = ? 
        LIMIT 1
    ");
    $old_data_stmt->bind_param("iii", $expense_id, $user_id, $company_id);
    $old_data_stmt->execute();
    $old_result = $old_data_stmt->get_result();
    
    if ($old_result->num_rows === 0) {
        throw new Exception('Expense not found or access denied');
    }
    
    $old_data = $old_result->fetch_assoc();
    $old_data_stmt->close();

    // Get account_title_id for the new category
    $title_stmt = $con->prepare("
        SELECT title_id 
        FROM account_titles 
        WHERE company_id = ? AND title_name = ? AND type = 'expense'
        LIMIT 1
    ");
    $title_stmt->bind_param("is", $company_id, $category);
    $title_stmt->execute();
    $title_result = $title_stmt->get_result();

    if ($title_result->num_rows === 0) {
        throw new Exception('Invalid expense category');
    }

    $account_title_id = $title_result->fetch_assoc()['title_id'];
    $title_stmt->close();

    // Update the expense
    $update_stmt = $con->prepare("
        UPDATE expenses 
        SET date = ?, particulars = ?, account_title_id = ?, amount = ? 
        WHERE expense_id = ? AND user_id = ? AND company_id = ?
    ");
    $update_stmt->bind_param("ssiiii", $date, $particulars, $account_title_id, $amount, $expense_id, $user_id, $company_id);
    $success = $update_stmt->execute();
    $update_stmt->close();

    if ($success) {
        // Record in work history
        $details = json_encode([
            'expense_id' => $expense_id,
            'old_data' => [
                'date' => $old_data['date'],
                'particulars' => $old_data['particulars'],
                'category' => $old_data['category'],
                'amount' => $old_data['amount']
            ],
            'new_data' => [
                'date' => $date,
                'particulars' => $particulars,
                'category' => $category,
                'amount' => $amount
            ]
        ]);

        $history_stmt = $con->prepare("INSERT INTO user_work_history (user_id, action_type, details) VALUES (?, 'expense_updated', ?)");
        $history_stmt->bind_param("is", $user_id, $details);
        $history_stmt->execute();
        $history_stmt->close();

        $con->commit();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to update expense");
    }
} catch (Exception $e) {
    $con->rollback();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$con->close();
?> 
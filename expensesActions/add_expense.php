<?php
session_start();
require_once '../connection.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Debug: Print received data
error_log("POST data: " . print_r($_POST, true));
error_log("Session data: " . print_r($_SESSION, true));

// Validate input
if (!isset($_POST['date']) || !isset($_POST['particulars']) || !isset($_POST['category']) || !isset($_POST['amount'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$user_id = $_SESSION['user_id'];
$date = $_POST['date'];
$particulars = $_POST['particulars'];
$category = $_POST['category'];
$amount = floatval($_POST['amount']);
$company_id = $_SESSION['selected_company_id'] ?? null;

// Debug: Print processed data
error_log("Processed data: user_id=$user_id, date=$date, category=$category, amount=$amount, company_id=$company_id");

// Verify user has access to this company
if (!$company_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No company selected']);
    exit();
}

// Get account_title_id from title_name
$title_stmt = $con->prepare("SELECT title_id FROM account_titles WHERE company_id = ? AND title_name = ? AND type = 'expense' LIMIT 1");
if (!$title_stmt) {
    error_log("Title statement prepare error: " . $con->error);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}

$title_stmt->bind_param("is", $company_id, $category);
if (!$title_stmt->execute()) {
    error_log("Title statement execute error: " . $title_stmt->error);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to get account title']);
    exit();
}
$title_result = $title_stmt->get_result();

if ($title_result->num_rows === 0) {
    error_log("No account title found for category: $category in company: $company_id");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid expense category']);
    exit();
}

$account_title_id = $title_result->fetch_assoc()['title_id'];
$title_stmt->close();

error_log("Found account_title_id: $account_title_id");

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
    // Insert the expense
    $stmt = $con->prepare("INSERT INTO expenses (user_id, company_id, account_title_id, date, particulars, amount) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $con->error);
    }
    
    error_log("About to execute insert with: user_id=$user_id, company_id=$company_id, account_title_id=$account_title_id, date=$date, particulars=$particulars, amount=$amount");
    
    $stmt->bind_param("iiissd", $user_id, $company_id, $account_title_id, $date, $particulars, $amount);
    $success = $stmt->execute();
    
    if (!$success) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $expense_id = $stmt->insert_id;
    $stmt->close();

    // Try to record in work history (but don't fail if it doesn't work)
    try {
        $details = json_encode([
            'expense_id' => $expense_id,
            'date' => $date,
            'particulars' => $particulars,
            'category' => $category,
            'amount' => $amount
        ]);

        $history_stmt = $con->prepare("INSERT INTO user_work_history (user_id, action_type, details) VALUES (?, 'expense_added', ?)");
        if ($history_stmt) {
            $history_stmt->bind_param("is", $user_id, $details);
            $history_stmt->execute();
            $history_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Failed to record work history (non-critical): " . $e->getMessage());
    }

    $con->commit();
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Error in transaction: " . $e->getMessage());
    $con->rollback();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$con->close();
?> 
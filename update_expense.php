<?php
session_start();
require_once 'connection.php';

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

// Debug log
error_log("Attempting to update expense_id: $expense_id for user_id: $user_id");
error_log("New values - Date: $date, Particulars: $particulars, Category: $category, Amount: $amount");

// First, verify the expense exists and belongs to this user
$check_stmt = $con->prepare("SELECT expense_id FROM expenses WHERE expense_id = ? AND user_id = ? LIMIT 1");
$check_stmt->bind_param("ii", $expense_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Expense not found or not authorized']);
    $check_stmt->close();
    $con->close();
    exit();
}
$check_stmt->close();

// Update the specific expense
$stmt = $con->prepare("UPDATE expenses SET date = ?, particulars = ?, category = ?, amount = ? WHERE expense_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("sssdii", $date, $particulars, $category, $amount, $expense_id, $user_id);

$success = $stmt->execute();

// Debug log
error_log("Update result: " . ($success ? "Success" : "Failed") . ", Affected rows: " . $stmt->affected_rows);

header('Content-Type: application/json');
echo json_encode([
    'success' => $success && $stmt->affected_rows > 0,
    'message' => $success && $stmt->affected_rows > 0 ? 'Expense updated successfully' : 'No changes made or update failed'
]);

$stmt->close();
$con->close();
?> 
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
if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing expense ID']);
    exit();
}

$user_id = $_SESSION['user_id'];
$expense_id = intval($_GET['id']);

// Debug log
error_log("Attempting to delete expense_id: $expense_id for user_id: $user_id");

// Delete the specific expense for this user
$stmt = $con->prepare("DELETE FROM expenses WHERE expense_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $expense_id, $user_id);

$success = $stmt->execute();

// Debug log
error_log("Delete result: " . ($success ? "Success" : "Failed") . ", Affected rows: " . $stmt->affected_rows);

header('Content-Type: application/json');
echo json_encode([
    'success' => $success && $stmt->affected_rows > 0,
    'message' => $success && $stmt->affected_rows > 0 ? 'Expense deleted successfully' : 'No expense found or not authorized'
]);

$stmt->close();
$con->close();
?> 
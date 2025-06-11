<?php
session_start();
require_once '../connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Validate input
if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing expense ID']);
    exit();
}

$user_id = $_SESSION['user_id'];
$expense_id = intval($_GET['id']);

// Fetch the expense
$stmt = $con->prepare("
    SELECT 
        e.expense_id,
        e.date,
        e.particulars,
        e.amount,
        at.title_name as category,
        at.title_id as account_title_id
    FROM expenses e
    JOIN account_titles at ON e.account_title_id = at.title_id
    WHERE e.expense_id = ? AND e.user_id = ?
");
$stmt->bind_param("ii", $expense_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Expense not found']);
    exit();
}

$expense = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode([
    'id' => $expense['expense_id'],
    'date' => $expense['date'],
    'particulars' => $expense['particulars'],
    'category' => $expense['category'],
    'account_title_id' => $expense['account_title_id'],
    'amount' => $expense['amount']
]);

$stmt->close();
$con->close();
?> 
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

// Get POST data
$date = $_POST['date'] ?? null;
$particulars = $_POST['particulars'] ?? null;
$category = $_POST['category'] ?? null;
$amount = $_POST['amount'] ?? null;

// Validate required fields
if (!$date || !$particulars || !$category || !$amount) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Get title_id for the category
$title_stmt = $con->prepare("
    SELECT title_id 
    FROM account_titles 
    WHERE company_id = ? AND title_name = ? AND type = 'sale'
");
$title_stmt->bind_param("is", $company_id, $category);
$title_stmt->execute();
$title_result = $title_stmt->get_result();

if ($title_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid category']);
    $title_stmt->close();
    $con->close();
    exit();
}

$title_id = $title_result->fetch_assoc()['title_id'];
$title_stmt->close();

// Insert the sale
$stmt = $con->prepare("
    INSERT INTO sales (company_id, title_id, date, particulars, amount) 
    VALUES (?, ?, ?, ?, ?)
");

$stmt->bind_param("iissd", $company_id, $title_id, $date, $particulars, $amount);
$success = $stmt->execute();

header('Content-Type: application/json');
echo json_encode(['success' => $success]);

$stmt->close();
$con->close();
?> 
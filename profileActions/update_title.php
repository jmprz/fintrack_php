<?php
session_start();
require_once '../connection.php';

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

$title_id = intval($_POST['title_id']);
$user_id = $_SESSION['user_id'];

// Verify user has access to this title's company
$verify_stmt = $con->prepare("
    SELECT 1 
    FROM account_titles t
    JOIN companies c ON t.company_id = c.company_id
    JOIN user_companies uc ON c.company_id = uc.company_id
    WHERE t.title_id = ? AND uc.user_id = ?
");
$verify_stmt->bind_param("ii", $title_id, $user_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Title not found or access denied']);
    $verify_stmt->close();
    $con->close();
    exit();
}
$verify_stmt->close();

// Update account title
$stmt = $con->prepare("
    UPDATE account_titles 
    SET title_name = ?
    WHERE title_id = ?
");

$title_name = trim($_POST['title_name']);

$stmt->bind_param("si", $title_name, $title_id);
$success = $stmt->execute();

header('Content-Type: application/json');
if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating category']);
}

$stmt->close();
$con->close();
?> 
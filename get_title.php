<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Title ID not provided']);
    exit();
}

$title_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Verify user has access to this title's company
$stmt = $con->prepare("
    SELECT t.* 
    FROM account_titles t
    JOIN companies c ON t.company_id = c.company_id
    JOIN user_companies uc ON c.company_id = uc.company_id
    WHERE t.title_id = ? AND uc.user_id = ?
");

$stmt->bind_param("ii", $title_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Title not found or access denied']);
    exit();
}

$title = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode($title);

$stmt->close();
$con->close();
?> 
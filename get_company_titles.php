<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['company_id']) || !isset($_GET['type'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$company_id = intval($_GET['company_id']);
$type = $_GET['type'];
$user_id = $_SESSION['user_id'];

// Verify user has access to this company
$verify_stmt = $con->prepare("
    SELECT 1 FROM user_companies 
    WHERE user_id = ? AND company_id = ?
");
$verify_stmt->bind_param("ii", $user_id, $company_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Company not found or access denied']);
    $verify_stmt->close();
    $con->close();
    exit();
}
$verify_stmt->close();

// Get account titles
$stmt = $con->prepare("
    SELECT * FROM account_titles 
    WHERE company_id = ? AND type = ?
    ORDER BY title_name
");

if (!$stmt) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $con->error]);
    $con->close();
    exit();
}

$stmt->bind_param("is", $company_id, $type);
if (!$stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to execute query: ' . $stmt->error]);
    $stmt->close();
    $con->close();
    exit();
}

$result = $stmt->get_result();
if (!$result) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to get result: ' . $stmt->error]);
    $stmt->close();
    $con->close();
    exit();
}

$titles = [];
while ($row = $result->fetch_assoc()) {
    $titles[] = $row;
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $titles]);

$stmt->close();
$con->close();
?> 
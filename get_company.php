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
    echo json_encode(['success' => false, 'message' => 'Company ID not provided']);
    exit();
}

$company_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Verify user has access to this company
$stmt = $con->prepare("
    SELECT c.* 
    FROM companies c
    JOIN user_companies uc ON c.company_id = uc.company_id
    WHERE c.company_id = ? AND uc.user_id = ?
");

$stmt->bind_param("ii", $company_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Company not found or access denied']);
    exit();
}

$company = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode($company);

$stmt->close();
$con->close();
?> 
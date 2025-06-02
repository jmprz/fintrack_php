<?php
session_start();
require_once 'connection.php';

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

$company_id = intval($_POST['company_id']);
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

// Update company details
$stmt = $con->prepare("
    UPDATE companies 
    SET company_name = ?, 
        address = ?, 
        contact_number = ?
    WHERE company_id = ?
");

$company_name = trim($_POST['company_name']);
$address = trim($_POST['address']);
$contact_number = trim($_POST['contact_number']);

$stmt->bind_param("sssi", $company_name, $address, $contact_number, $company_id);
$success = $stmt->execute();

header('Content-Type: application/json');
if ($success) {
    // If this was the selected company, update the session variable
    if (isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] == $company_id) {
        $_SESSION['selected_company_name'] = $company_name;
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating company']);
}

$stmt->close();
$con->close();
?> 
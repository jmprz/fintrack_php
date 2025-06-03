<?php
session_start();
require_once '../connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

// Check if company is selected
if (!isset($_SESSION['selected_company_id'])) {
    die(json_encode(['success' => false, 'message' => 'No company selected']));
}

if (!isset($_GET['id'])) {
    die(json_encode(['success' => false, 'message' => 'No entry ID provided']));
}

$id = intval($_GET['id']);
$company_id = $_SESSION['selected_company_id'];

try {
    $query = "SELECT * FROM trial_balance WHERE id = ? AND company_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("ii", $id, $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'entry' => $row
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Entry not found or access denied'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$stmt->close();
$con->close();
?> 
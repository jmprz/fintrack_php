<?php
session_start();
require_once '../connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not authenticated']));
}

// Check if company is selected
if (!isset($_SESSION['selected_company_id'])) {
    die(json_encode(['error' => 'No company selected']));
}

$company_id = $_SESSION['selected_company_id'];
$year = isset($_POST['year']) ? intval($_POST['year']) : 2025;

try {
    $query = "SELECT * FROM trial_balance WHERE company_id = ? AND year = ? ORDER BY classification ASC, category ASC";
    $stmt = $con->prepare($query);
    $stmt->bind_param("ii", $company_id, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode([
        'data' => $data
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$stmt->close();
$con->close();
?> 
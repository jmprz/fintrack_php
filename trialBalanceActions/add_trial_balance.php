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

// Validate required fields
if (!isset($_POST['classification']) || !isset($_POST['account_code_sap']) || !isset($_POST['description']) || !isset($_POST['ending_balance'])) {
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

$company_id = $_SESSION['selected_company_id'];
$year = isset($_POST['year']) ? intval($_POST['year']) : 2025;
$classification = $_POST['classification'];
$category = isset($_POST['category']) ? $_POST['category'] : null;
$account_code_sap = $_POST['account_code_sap'];
$description = $_POST['description'];
$ending_balance = floatval($_POST['ending_balance']);

// Validate category based on classification
if (strpos($classification, 'Total') !== false && $category !== null) {
    die(json_encode(['success' => false, 'message' => 'Category must be null for Total classifications']));
}

try {
    $query = "INSERT INTO trial_balance (company_id, year, classification, category, account_code_sap, description, ending_balance) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $con->prepare($query);
    $stmt->bind_param("iissssd", $company_id, $year, $classification, $category, $account_code_sap, $description, $ending_balance);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Entry added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add entry']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$stmt->close();
$con->close();
?> 
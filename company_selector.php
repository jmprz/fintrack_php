<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['company_id'])) {
        $company_id = intval($_POST['company_id']);
        
        // Verify that this user has access to this company
        $stmt = $con->prepare("
            SELECT c.company_id, c.company_name 
            FROM companies c 
            JOIN user_companies uc ON c.company_id = uc.company_id 
            WHERE uc.user_id = ? AND c.company_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $user_id, $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $company = $result->fetch_assoc();
            $_SESSION['selected_company_id'] = $company['company_id'];
            $_SESSION['selected_company_name'] = $company['company_name'];
            
            echo json_encode([
                'success' => true,
                'company_id' => $company['company_id'],
                'company_name' => $company['company_name']
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Company not found or access denied']);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Company ID not provided']);
    }
} else {
    // GET request - return list of companies user has access to
    $stmt = $con->prepare("
        SELECT c.company_id, c.company_name 
        FROM companies c 
        JOIN user_companies uc ON c.company_id = uc.company_id 
        WHERE uc.user_id = ?
        ORDER BY c.company_name
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $companies = [];
    while ($row = $result->fetch_assoc()) {
        $companies[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'companies' => $companies,
        'selected_company_id' => $_SESSION['selected_company_id'] ?? null,
        'selected_company_name' => $_SESSION['selected_company_name'] ?? null
    ]);
    
    $stmt->close();
}

$con->close();
?> 
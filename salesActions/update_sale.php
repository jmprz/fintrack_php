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

// Get sale ID from URL
$sale_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$sale_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid sale ID']);
    exit();
}

// Start transaction
$con->begin_transaction();

try {
    // First, get the old sale data
    $old_data_stmt = $con->prepare("
        SELECT s.*, at.title_name as category
        FROM sales s
        JOIN account_titles at ON s.title_id = at.title_id
        WHERE s.sale_id = ? AND s.company_id = ?
    ");
    $old_data_stmt->bind_param("ii", $sale_id, $company_id);
    $old_data_stmt->execute();
    $old_result = $old_data_stmt->get_result();

    if ($old_result->num_rows === 0) {
        throw new Exception('Sale not found or access denied');
    }

    $old_data = $old_result->fetch_assoc();
    $old_data_stmt->close();

    // Get POST data
    $date = $_POST['date'] ?? null;
    $particulars = $_POST['particulars'] ?? null;
    $category = $_POST['category'] ?? null;
    $amount = $_POST['amount'] ?? null;

    // Validate required fields
    if (!$date || !$particulars || !$category || !$amount) {
        throw new Exception('All fields are required');
    }

    // Get title_id for the new category
    $title_stmt = $con->prepare("
        SELECT title_id 
        FROM account_titles 
        WHERE company_id = ? AND title_name = ? AND type = 'sale'
    ");
    $title_stmt->bind_param("is", $company_id, $category);
    $title_stmt->execute();
    $title_result = $title_stmt->get_result();

    if ($title_result->num_rows === 0) {
        throw new Exception('Invalid category');
    }

    $title_id = $title_result->fetch_assoc()['title_id'];
    $title_stmt->close();

    // Update the sale
    $update_stmt = $con->prepare("
        UPDATE sales 
        SET title_id = ?, date = ?, particulars = ?, amount = ?
        WHERE sale_id = ? AND company_id = ?
    ");
    $update_stmt->bind_param("issdii", $title_id, $date, $particulars, $amount, $sale_id, $company_id);
    $success = $update_stmt->execute();
    $update_stmt->close();

    if ($success) {
        // Record in work history
        $details = json_encode([
            'sale_id' => $sale_id,
            'old_data' => [
                'date' => $old_data['date'],
                'particulars' => $old_data['particulars'],
                'category' => $old_data['category'],
                'amount' => $old_data['amount']
            ],
            'new_data' => [
                'date' => $date,
                'particulars' => $particulars,
                'category' => $category,
                'amount' => $amount
            ]
        ]);

        $history_stmt = $con->prepare("INSERT INTO user_work_history (user_id, action_type, details) VALUES (?, 'sale_updated', ?)");
        $history_stmt->bind_param("is", $_SESSION['user_id'], $details);
        $history_stmt->execute();
        $history_stmt->close();

        $con->commit();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to update sale");
    }
} catch (Exception $e) {
    $con->rollback();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$con->close();
?> 
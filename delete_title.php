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

$title_id = intval($_POST['title_id']);
$user_id = $_SESSION['user_id'];

// Start transaction
$con->begin_transaction();

try {
    // Verify user has access to this title's company
    $verify_stmt = $con->prepare("
        SELECT t.type, t.company_id 
        FROM account_titles t
        JOIN companies c ON t.company_id = c.company_id
        JOIN user_companies uc ON c.company_id = uc.company_id
        WHERE t.title_id = ? AND uc.user_id = ?
    ");
    $verify_stmt->bind_param("ii", $title_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {
        throw new Exception('Title not found or access denied');
    }

    $title_info = $verify_result->fetch_assoc();
    $verify_stmt->close();

    // Delete related records first
    if ($title_info['type'] === 'expense') {
        $stmt = $con->prepare("DELETE FROM expenses WHERE category = (SELECT title_name FROM account_titles WHERE title_id = ?) AND company_id = ?");
        $stmt->bind_param("ii", $title_id, $title_info['company_id']);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $con->prepare("DELETE FROM sales WHERE category = (SELECT title_name FROM account_titles WHERE title_id = ?) AND company_id = ?");
        $stmt->bind_param("ii", $title_id, $title_info['company_id']);
        $stmt->execute();
        $stmt->close();
    }

    // Delete the account title
    $stmt = $con->prepare("DELETE FROM account_titles WHERE title_id = ?");
    $stmt->bind_param("i", $title_id);
    $stmt->execute();
    $stmt->close();

    $con->commit();
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $con->rollback();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$con->close();
?> 
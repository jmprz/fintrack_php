<?php
// Prevent any unwanted output
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

session_start();
require_once '../connection.php';

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized');
    }

    // Get parameters
    $month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $category_search = isset($_GET['category_search']) ? $_GET['category_search'] : '';
    $company_id = isset($_SESSION['selected_company_id']) ? $_SESSION['selected_company_id'] : null;

    if (!$company_id) {
        throw new Exception('No company selected');
    }

    // First, let's get the structure of our tables
    $describe_expenses = $con->query("DESCRIBE expenses");
    $describe_account_titles = $con->query("DESCRIBE account_titles");
    
    if (!$describe_expenses || !$describe_account_titles) {
        throw new Exception('Could not get table structure');
    }

    $expenses_columns = [];
    while ($row = $describe_expenses->fetch_assoc()) {
        $expenses_columns[] = $row['Field'];
    }

    $account_titles_columns = [];
    while ($row = $describe_account_titles->fetch_assoc()) {
        $account_titles_columns[] = $row['Field'];
    }

    // Build the base query with correct column names
    $query = "SELECT 
        COALESCE(SUM(e.amount), 0) as total,
        COALESCE(SUM(CASE WHEN at.title_name NOT IN ('LOAN AMORTIZATION', 'UNIFORM') THEN e.amount ELSE 0 END), 0) as total_without_loan
    FROM expenses e
    JOIN account_titles at ON e.account_title_id = at.title_id
    WHERE MONTH(e.date) = ? AND YEAR(e.date) = ? AND e.company_id = ?";

    $params = [$month, $year, $company_id];
    $types = "iii";

    // Add category search if provided
    if (!empty($category_search)) {
        $query .= " AND at.title_name LIKE ?";
        $params[] = "%$category_search%";
        $types .= "s";
    }

    if (!$con) {
        throw new Exception('Database connection failed');
    }

    $stmt = $con->prepare($query);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $con->error);
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    echo json_encode([
        'success' => true,
        'total' => $data['total'],
        'total_without_loan' => $data['total_without_loan'],
        'debug' => [
            'month' => $month,
            'year' => $year,
            'company_id' => $company_id,
            'category_search' => $category_search,
            'expenses_columns' => $expenses_columns,
            'account_titles_columns' => $account_titles_columns
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'total' => 0,
        'total_without_loan' => 0
    ]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($con)) {
        $con->close();
    }
}
?> 
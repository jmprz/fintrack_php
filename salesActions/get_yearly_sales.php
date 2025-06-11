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

// Get year parameter
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get all sales categories for the company
$categories_stmt = $con->prepare("
    SELECT DISTINCT at.title_id, at.title_name
    FROM account_titles at
    WHERE at.company_id = ? AND at.type = 'sale'
    ORDER BY at.title_name
");
$categories_stmt->bind_param("i", $company_id);
$categories_stmt->execute();
$categories_result = $categories_stmt->get_result();

$summary = [];

while ($category = $categories_result->fetch_assoc()) {
    // Get monthly totals for each category
    $monthly_stmt = $con->prepare("
        SELECT MONTH(s.date) as month, SUM(s.amount) as total
        FROM sales s
        WHERE s.company_id = ? 
        AND s.title_id = ?
        AND YEAR(s.date) = ?
        GROUP BY MONTH(s.date)
    ");
    $monthly_stmt->bind_param("iii", $company_id, $category['title_id'], $year);
    $monthly_stmt->execute();
    $monthly_result = $monthly_stmt->get_result();

    // Initialize monthly totals array with zeros
    $monthly_totals = array_fill(0, 12, 0);
    $total = 0;

    // Fill in actual values
    while ($month_data = $monthly_result->fetch_assoc()) {
        $month_index = intval($month_data['month']) - 1;
        $monthly_totals[$month_index] = floatval($month_data['total']);
        $total += floatval($month_data['total']);
    }

    $summary[] = [
        'account_title' => $category['title_name'],
        'monthly_totals' => $monthly_totals,
        'total' => $total
    ];

    $monthly_stmt->close();
}

$categories_stmt->close();
$con->close();

header('Content-Type: application/json');
echo json_encode($summary);
?> 
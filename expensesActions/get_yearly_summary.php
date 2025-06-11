<?php
session_start();
require_once '../connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$company_id = isset($_SESSION['selected_company_id']) ? $_SESSION['selected_company_id'] : null;

if (!$company_id) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Get all unique categories for the company
$categories_query = "SELECT DISTINCT category FROM expenses WHERE company_id = ? ORDER BY category";
$stmt = $con->prepare($categories_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$categories_result = $stmt->get_result();

$summary = [];

while ($category = $categories_result->fetch_assoc()) {
    // For each category, get monthly totals
    $monthly_query = "SELECT 
        MONTH(date) as month,
        COALESCE(SUM(amount), 0) as total
    FROM expenses 
    WHERE company_id = ? AND category = ? AND YEAR(date) = ?
    GROUP BY MONTH(date)";

    $stmt = $con->prepare($monthly_query);
    $stmt->bind_param("isi", $company_id, $category['category'], $year);
    $stmt->execute();
    $monthly_result = $stmt->get_result();

    // Initialize monthly totals array with zeros
    $monthly_totals = array_fill(0, 12, 0);
    $total = 0;

    // Fill in the actual values
    while ($month_data = $monthly_result->fetch_assoc()) {
        $month_index = intval($month_data['month']) - 1;
        $monthly_totals[$month_index] = floatval($month_data['total']);
        $total += floatval($month_data['total']);
    }

    $summary[] = [
        'account_title' => $category['category'],
        'monthly_totals' => $monthly_totals,
        'total' => $total
    ];
}

// Sort summary by account title
usort($summary, function($a, $b) {
    return strcmp($a['account_title'], $b['account_title']);
});

header('Content-Type: application/json');
echo json_encode($summary);

$stmt->close();
$con->close();
?> 
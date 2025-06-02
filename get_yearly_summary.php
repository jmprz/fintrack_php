<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get all account titles first
$titles_query = "SELECT DISTINCT category as account_title FROM expenses WHERE user_id = ? AND YEAR(date) = ? ORDER BY category";
$stmt = $con->prepare($titles_query);
$stmt->bind_param("ii", $user_id, $year);
$stmt->execute();
$result = $stmt->get_result();
$account_titles = [];
while ($row = $result->fetch_assoc()) {
    $account_titles[] = $row['account_title'];
}
$stmt->close();

$data = [];

// For each account title, get monthly totals
foreach ($account_titles as $title) {
    $monthly_query = "SELECT 
        MONTH(date) as month,
        COALESCE(SUM(amount), 0) as total
    FROM expenses 
    WHERE user_id = ? 
    AND YEAR(date) = ? 
    AND category = ?
    GROUP BY MONTH(date)
    ORDER BY MONTH(date)";

    $stmt = $con->prepare($monthly_query);
    $stmt->bind_param("iis", $user_id, $year, $title);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Initialize array with zeros for all months
    $monthly_totals = array_fill(0, 12, 0);
    
    // Fill in actual values
    while ($row = $result->fetch_assoc()) {
        $month_index = $row['month'] - 1; // Convert 1-based month to 0-based index
        $monthly_totals[$month_index] = floatval($row['total']);
    }
    
    $total = array_sum($monthly_totals);
    
    $data[] = [
        'account_title' => $title,
        'monthly_totals' => $monthly_totals,
        'total' => $total
    ];
    
    $stmt->close();
}

// Sort data by total amount descending
usort($data, function($a, $b) {
    return $b['total'] - $a['total'];
});

header('Content-Type: application/json');
echo json_encode($data);

$con->close();
?> 
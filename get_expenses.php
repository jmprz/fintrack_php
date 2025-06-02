<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Unauthorized'
    ]);
    exit();
}

// Debug: Print the session info
error_log("User ID: " . $_SESSION['user_id']);

$user_id = $_SESSION['user_id'];
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$category_search = isset($_GET['category_search']) ? $_GET['category_search'] : '';

// Debug: Print the query parameters
error_log("Month: $month, Year: $year, Category Search: $category_search");

// Get total records before filtering
$total_query = "SELECT COUNT(*) as count FROM expenses WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";
$params = [$user_id, $month, $year];
$types = "iii";

if (!empty($category_search)) {
    $total_query .= " AND category LIKE ?";
    $params[] = "%$category_search%";
    $types .= "s";
}

$total_stmt = $con->prepare($total_query);
$total_stmt->bind_param($types, ...$params);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_records = $total_result->fetch_assoc()['count'];
$total_stmt->close();

// Handle DataTables parameters
$draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$length = isset($_GET['length']) ? intval($_GET['length']) : 200;
$search = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';
$order_column = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 0;
$order_dir = isset($_GET['order'][0]['dir']) ? $_GET['order'][0]['dir'] : 'ASC';

// Debug: Print DataTables parameters
error_log("Draw: $draw, Start: $start, Length: $length");

// Column names for ordering
$columns = ['date', 'particulars', 'category', 'amount'];
$order_column_name = $columns[$order_column] ?? 'date';

// Prepare the base query
$query = "SELECT * FROM expenses WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";
$params = [$user_id, $month, $year];
$types = "iii";

// Add category search if provided
if (!empty($category_search)) {
    $query .= " AND category LIKE ?";
    $params[] = "%$category_search%";
    $types .= "s";
}

// Get filtered records count (same as total in this case)
$filtered_records = $total_records;

// Add ordering and limit
$query .= " ORDER BY $order_column_name $order_dir LIMIT ?, ?";
$params = array_merge($params, [$start, $length]);
$types .= "ii";

// Debug: Print the final query and parameters
error_log("Query: $query");
error_log("Parameters: " . print_r($params, true));

// Execute final query
$stmt = $con->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id' => $row['expense_id'],
        'date' => $row['date'],
        'particulars' => $row['particulars'],
        'category' => $row['category'],
        'amount' => $row['amount']
    ];
}

$response = [
    'draw' => $draw,
    'recordsTotal' => $total_records,
    'recordsFiltered' => $filtered_records,
    'data' => $data
];

// Debug: Print the final response
error_log("Response: " . json_encode($response));

header('Content-Type: application/json');
echo json_encode($response);

$stmt->close();
$con->close();
?> 
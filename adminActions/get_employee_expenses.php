<?php
session_start();
require_once '../connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get viewing parameters from session
$viewing_company_id = isset($_SESSION['viewing_company_id']) ? $_SESSION['viewing_company_id'] : null;
$viewing_employee_id = isset($_SESSION['viewing_employee_id']) ? $_SESSION['viewing_employee_id'] : null;

if (!$viewing_company_id || !$viewing_employee_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No company or employee selected']);
    exit();
}

// Verify the employee has access to this company
$verify_stmt = $con->prepare("
    SELECT 1 
    FROM user_companies 
    WHERE user_id = ? AND company_id = ?
    LIMIT 1
");
$verify_stmt->bind_param("ii", $viewing_employee_id, $viewing_company_id);
$verify_stmt->execute();
$result = $verify_stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Employee does not have access to this company']);
    exit();
}
$verify_stmt->close();

// Get parameters
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$category_search = isset($_GET['category_search']) ? $_GET['category_search'] : '';

// Get total number of records
$total_records_stmt = $con->prepare("
    SELECT COUNT(*) as total 
    FROM expenses e
    WHERE e.company_id = ?
    AND MONTH(e.date) = ? 
    AND YEAR(e.date) = ?
");
$total_records_stmt->bind_param("iii", $viewing_company_id, $month, $year);
$total_records_stmt->execute();
$total_records = $total_records_stmt->get_result()->fetch_assoc()['total'];
$total_records_stmt->close();

// Build base query
$query = "
    SELECT e.*, at.title_name as category 
    FROM expenses e
    JOIN account_titles at ON e.account_title_id = at.title_id
    WHERE e.company_id = ?
    AND MONTH(e.date) = ? 
    AND YEAR(e.date) = ?
";

$params = [$viewing_company_id, $month, $year];
$types = "iii";

// Add category search if provided
if ($category_search) {
    $query .= " AND at.title_name LIKE ?";
    $params[] = "%$category_search%";
    $types .= "s";
}

// Get total number of filtered records
$filtered_records_stmt = $con->prepare("SELECT COUNT(*) as total FROM (" . $query . ") as filtered");
$filtered_records_stmt->bind_param($types, ...$params);
$filtered_records_stmt->execute();
$filtered_records = $filtered_records_stmt->get_result()->fetch_assoc()['total'];
$filtered_records_stmt->close();

// Add sorting
$order_column = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 0;
$order_dir = isset($_GET['order'][0]['dir']) ? $_GET['order'][0]['dir'] : 'asc';

$columns = ['e.date', 'e.particulars', 'at.title_name', 'e.amount'];
if (isset($columns[$order_column])) {
    $query .= " ORDER BY " . $columns[$order_column] . " " . ($order_dir === 'desc' ? 'DESC' : 'ASC');
} else {
    $query .= " ORDER BY e.date DESC";
}

// Add pagination
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$length = isset($_GET['length']) ? intval($_GET['length']) : 10;
$query .= " LIMIT ?, ?";
$params[] = $start;
$params[] = $length;
$types .= "ii";

// Prepare and execute query
$stmt = $con->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Format data for DataTables
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

// Prepare response
$response = [
    'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 0,
    'recordsTotal' => $total_records,
    'recordsFiltered' => $filtered_records,
    'data' => $data
];

header('Content-Type: application/json');
echo json_encode($response);

$stmt->close();
$con->close();
?> 
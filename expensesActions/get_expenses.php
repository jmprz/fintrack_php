<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../connection.php';

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
$is_employee_view = isset($_GET['employee_view']) && $_GET['employee_view'] === 'true';

// Get company access
if ($is_employee_view) {
    // Get all companies the employee has access to
    $companies_stmt = $con->prepare("
        SELECT GROUP_CONCAT(company_id) as company_ids
        FROM user_companies
        WHERE user_id = ?
    ");
    $companies_stmt->bind_param("i", $user_id);
    $companies_stmt->execute();
    $result = $companies_stmt->get_result();
    $row = $result->fetch_assoc();
    $company_ids = $row['company_ids'];
    $companies_stmt->close();

    if (!$company_ids) {
        header('Content-Type: application/json');
        echo json_encode([
            'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'No companies assigned'
        ]);
        exit();
    }
} else {
    // Regular view - use selected company
    $company_ids = isset($_GET['company_id']) ? intval($_GET['company_id']) : ($_SESSION['selected_company_id'] ?? null);
    
    if (!$company_ids) {
        header('Content-Type: application/json');
        echo json_encode([
            'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => []
        ]);
        exit();
    }
}

// Verify user has access to this company
$verify_stmt = $con->prepare("
    SELECT 1 FROM user_companies 
    WHERE user_id = ? AND company_id = ? 
    LIMIT 1
");
$verify_stmt->bind_param("ii", $user_id, $company_ids);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode([
        'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
    $verify_stmt->close();
    $con->close();
    exit();
}
$verify_stmt->close();

// Debug: Print the query parameters
error_log("Month: $month, Year: $year, Category Search: $category_search, Company ID: $company_ids");

try {
    // Get total records before filtering
    $total_query = "
        SELECT COUNT(*) as count 
        FROM expenses e
        JOIN account_titles at ON e.account_title_id = at.title_id
        WHERE e.company_id IN (" . $company_ids . ") 
        AND MONTH(e.date) = ? AND YEAR(e.date) = ?";
    $params = [$month, $year];
    $types = "ii";

    if (!empty($category_search)) {
        $total_query .= " AND at.title_name LIKE ?";
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

    // Column names for ordering
    $columns = ['e.date', 'e.particulars', 'at.title_name', 'e.amount'];
    $order_column_name = $columns[$order_column] ?? 'e.date';

    // Prepare the base query
    $query = "
        SELECT 
            e.expense_id,
            e.date,
            e.particulars,
            e.amount,
            at.title_name as category
        FROM expenses e
        JOIN account_titles at ON e.account_title_id = at.title_id
        WHERE e.company_id IN (" . $company_ids . ") 
        AND MONTH(e.date) = ? AND YEAR(e.date) = ?";
    $params = [$month, $year];
    $types = "ii";

    // Add category search if provided
    if (!empty($category_search)) {
        $query .= " AND at.title_name LIKE ?";
        $params[] = "%$category_search%";
        $types .= "s";
    }

    // Get filtered records count (same as total in this case)
    $filtered_records = $total_records;

    // Add ordering and limit
    $query .= " ORDER BY $order_column_name $order_dir LIMIT ?, ?";
    $params = array_merge($params, [$start, $length]);
    $types .= "ii";

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

    header('Content-Type: application/json');
    echo json_encode($response);

    $stmt->close();
} catch (Exception $e) {
    error_log("Error in get_expenses.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'An error occurred while fetching data'
    ]);
}

$con->close();
?> 
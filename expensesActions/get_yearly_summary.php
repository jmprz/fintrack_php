<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../connection.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized');
    }

    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $company_id = isset($_SESSION['selected_company_id']) ? $_SESSION['selected_company_id'] : null;

    if (!$company_id) {
        echo json_encode([]);
        exit();
    }

    // Check connection
    if ($con->connect_error) {
        throw new Exception("Connection failed: " . $con->connect_error);
    }

    // Get all unique account titles for the company's expenses
    $titles_query = "
        SELECT DISTINCT at.title_id, at.title_name 
        FROM account_titles at
        INNER JOIN expenses e ON e.account_title_id = at.title_id
        WHERE at.company_id = ? AND at.type = 'expense'
        ORDER BY at.title_name";
    
    $stmt = $con->prepare($titles_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $con->error);
    }

    $stmt->bind_param("i", $company_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $titles_result = $stmt->get_result();
    $summary = [];

    while ($title = $titles_result->fetch_assoc()) {
        // For each account title, get monthly totals
        $monthly_query = "
            SELECT 
                MONTH(e.date) as month,
                COALESCE(SUM(e.amount), 0) as total
            FROM expenses e
            WHERE e.company_id = ? 
            AND e.account_title_id = ? 
            AND YEAR(e.date) = ?
            GROUP BY MONTH(e.date)";

        $monthly_stmt = $con->prepare($monthly_query);
        if (!$monthly_stmt) {
            throw new Exception("Prepare monthly query failed: " . $con->error);
        }

        $monthly_stmt->bind_param("iii", $company_id, $title['title_id'], $year);
        if (!$monthly_stmt->execute()) {
            throw new Exception("Execute monthly query failed: " . $monthly_stmt->error);
        }

        $monthly_result = $monthly_stmt->get_result();

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
            'account_title' => $title['title_name'],
            'monthly_totals' => $monthly_totals,
            'total' => $total
        ];

        $monthly_stmt->close();
    }

    // Sort summary by account title
    usort($summary, function($a, $b) {
        return strcmp($a['account_title'], $b['account_title']);
    });

    echo json_encode($summary);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($monthly_stmt)) $monthly_stmt->close();
    if (isset($stmt)) $stmt->close();
    if (isset($con)) $con->close();
}
?>
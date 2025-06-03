<?php
session_start();
require_once '../connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not authenticated']));
}

// Check if company is selected
if (!isset($_SESSION['selected_company_id'])) {
    die(json_encode(['error' => 'No company selected']));
}

$company_id = $_SESSION['selected_company_id'];
$current_year = isset($_POST['year']) ? intval($_POST['year']) : 2025;
$last_year = $current_year - 1;

try {
    // Get current year data
    $current_year_query = "SELECT classification, SUM(ending_balance) as total 
                          FROM trial_balance 
                          WHERE company_id = ? AND year = ?
                          GROUP BY classification";
    $stmt = $con->prepare($current_year_query);
    $stmt->bind_param("ii", $company_id, $current_year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $current_year_data = [];
    while ($row = $result->fetch_assoc()) {
        $current_year_data[$row['classification']] = $row['total'];
    }
    
    // Get last year data
    $last_year_query = "SELECT classification, SUM(ending_balance) as total 
                       FROM trial_balance 
                       WHERE company_id = ? AND year = ?
                       GROUP BY classification";
    $stmt = $con->prepare($last_year_query);
    $stmt->bind_param("ii", $company_id, $last_year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $last_year_data = [];
    while ($row = $result->fetch_assoc()) {
        $last_year_data[$row['classification']] = $row['total'];
    }
    
    // Calculate income statement rows
    $revenues = $current_year_data['Revenues'] ?? 0;
    $revenues_ly = $last_year_data['Revenues'] ?? 0;
    
    $cost_of_sales = $current_year_data['Cost of sales and services'] ?? 0;
    $cost_of_sales_ly = $last_year_data['Cost of sales and services'] ?? 0;
    
    $gross_profit = $revenues - $cost_of_sales;
    $gross_profit_ly = $revenues_ly - $cost_of_sales_ly;
    
    $admin_expenses = $current_year_data['Administrative expenses'] ?? 0;
    $admin_expenses_ly = $last_year_data['Administrative expenses'] ?? 0;
    
    $marketing_expenses = $current_year_data['Marketing expenses'] ?? 0;
    $marketing_expenses_ly = $last_year_data['Marketing expenses'] ?? 0;
    
    $finance_costs = $current_year_data['Finance costs'] ?? 0;
    $finance_costs_ly = $last_year_data['Finance costs'] ?? 0;
    
    $other_income = $current_year_data['Other income'] ?? 0;
    $other_income_ly = $last_year_data['Other income'] ?? 0;
    
    $profit_before_tax = $gross_profit + $other_income - $finance_costs - $admin_expenses - $marketing_expenses;
    $profit_before_tax_ly = $gross_profit_ly + $other_income_ly - $finance_costs_ly - $admin_expenses_ly - $marketing_expenses_ly;
    
    $income_tax = $current_year_data['Income Tax Expense'] ?? 0;
    $income_tax_ly = $last_year_data['Income Tax Expense'] ?? 0;
    
    $profit = $profit_before_tax - $income_tax;
    $profit_ly = $profit_before_tax_ly - $income_tax_ly;
    
    $other_comprehensive = $current_year_data['Other Comprehensive Income'] ?? 0;
    $other_comprehensive_ly = $last_year_data['Other Comprehensive Income'] ?? 0;
    
    $total_comprehensive = $profit + $other_comprehensive;
    $total_comprehensive_ly = $profit_ly + $other_comprehensive_ly;
    
    $pre_tax_margin = $revenues != 0 ? ($profit_before_tax / $revenues) * 100 : 0;
    $pre_tax_margin_ly = $revenues_ly != 0 ? ($profit_before_tax_ly / $revenues_ly) * 100 : 0;
    
    $data = [
        [
            'notes' => 'Revenues',
            'current_year' => $revenues,
            'last_year' => $revenues_ly
        ],
        [
            'notes' => 'Cost of Sales',
            'current_year' => $cost_of_sales,
            'last_year' => $cost_of_sales_ly
        ],
        [
            'notes' => 'Gross Profit',
            'current_year' => $gross_profit,
            'last_year' => $gross_profit_ly,
            'is_calculated' => true
        ],
        [
            'notes' => 'Administrative Expenses',
            'current_year' => $admin_expenses,
            'last_year' => $admin_expenses_ly
        ],
        [
            'notes' => 'Marketing Expenses',
            'current_year' => $marketing_expenses,
            'last_year' => $marketing_expenses_ly
        ],
        [
            'notes' => 'Finance Costs',
            'current_year' => $finance_costs,
            'last_year' => $finance_costs_ly
        ],
        [
            'notes' => 'Other Income',
            'current_year' => $other_income,
            'last_year' => $other_income_ly
        ],
        [
            'notes' => 'Profit Before Tax',
            'current_year' => $profit_before_tax,
            'last_year' => $profit_before_tax_ly,
            'is_calculated' => true
        ],
        [
            'notes' => 'Income Tax Expense',
            'current_year' => $income_tax,
            'last_year' => $income_tax_ly
        ],
        [
            'notes' => 'Profit',
            'current_year' => $profit,
            'last_year' => $profit_ly,
            'is_calculated' => true
        ],
        [
            'notes' => 'Other Comprehensive Income',
            'current_year' => $other_comprehensive,
            'last_year' => $other_comprehensive_ly
        ],
        [
            'notes' => 'Total Comprehensive Income',
            'current_year' => $total_comprehensive,
            'last_year' => $total_comprehensive_ly,
            'is_calculated' => true
        ],
        [
            'notes' => 'Pre-Tax Profit Margin',
            'current_year' => $pre_tax_margin,
            'last_year' => $pre_tax_margin_ly,
            'is_percentage' => true,
            'is_calculated' => true
        ]
    ];
    
    echo json_encode(['data' => $data]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$stmt->close();
$con->close();
?> 
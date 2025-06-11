<?php
session_start();
require_once '../connection.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['account_type'] !== 'Admin') {
    die(json_encode(['error' => 'Not authenticated']));
}

// Check if viewing employee and company are set
if (!isset($_SESSION['viewing_employee_id']) || !isset($_SESSION['viewing_company_id'])) {
    die(json_encode(['error' => 'No employee or company selected']));
}

$company_id = $_SESSION['viewing_company_id'];
$current_year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
$last_year = $current_year - 1;

try {
    // Get current year data
    $current_year_query = "SELECT notes, SUM(ending_balance) as total 
                          FROM trial_balance 
                          WHERE company_id = ? AND year = ?
                          GROUP BY notes";
    $stmt = $con->prepare($current_year_query);
    $stmt->bind_param("ii", $company_id, $current_year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $current_year_data = [];
    while ($row = $result->fetch_assoc()) {
        $current_year_data[$row['notes']] = $row['total'];
    }
    
    // Get last year data
    $last_year_query = "SELECT notes, SUM(ending_balance) as total 
                       FROM trial_balance 
                       WHERE company_id = ? AND year = ?
                       GROUP BY notes";
    $stmt = $con->prepare($last_year_query);
    $stmt->bind_param("ii", $company_id, $last_year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $last_year_data = [];
    while ($row = $result->fetch_assoc()) {
        $last_year_data[$row['notes']] = $row['total'];
    }
    
    // Helper function to get total for a note
    function getTotal($note, $data) {
        return isset($data[$note]) ? $data[$note] : 0;
    }
    
    $data = [];
    
    // Assets
    $data[] = ['notes' => 'Assets', 'current_year' => '', 'last_year' => '', 'is_header' => true, 'indent' => 0];
    $data[] = ['notes' => 'Current Assets', 'current_year' => '', 'last_year' => '', 'is_header' => true, 'indent' => 0];
    
    // Current Assets
    $cash = getTotal('Cash', $current_year_data);
    $cash_ly = getTotal('Cash', $last_year_data);
    $data[] = ['notes' => 'Cash', 'current_year' => $cash, 'last_year' => $cash_ly, 'indent' => 1];
    
    $receivables = getTotal('Trade and other receivables', $current_year_data);
    $receivables_ly = getTotal('Trade and other receivables', $last_year_data);
    $data[] = ['notes' => 'Trade and other receivables', 'current_year' => $receivables, 'last_year' => $receivables_ly, 'indent' => 1];
    
    $inventories = getTotal('Inventories', $current_year_data);
    $inventories_ly = getTotal('Inventories', $last_year_data);
    $data[] = ['notes' => 'Inventories', 'current_year' => $inventories, 'last_year' => $inventories_ly, 'indent' => 1];
    
    $prepayments = getTotal('Prepayments and other assets', $current_year_data);
    $prepayments_ly = getTotal('Prepayments and other assets', $last_year_data);
    $data[] = ['notes' => 'Prepayments and other assets', 'current_year' => $prepayments, 'last_year' => $prepayments_ly, 'indent' => 1];
    
    $total_current_assets = $cash + $receivables + $inventories + $prepayments;
    $total_current_assets_ly = $cash_ly + $receivables_ly + $inventories_ly + $prepayments_ly;
    $data[] = ['notes' => 'Total Current Assets', 'current_year' => $total_current_assets, 'last_year' => $total_current_assets_ly, 'indent' => 2, 'is_calculated' => true];
    
    // Non-current Assets
    $data[] = ['notes' => 'Non-current Assets', 'current_year' => '', 'last_year' => '', 'is_header' => true, 'indent' => 0];
    
    $ppe = getTotal('Property and equipment-net', $current_year_data);
    $ppe_ly = getTotal('Property and equipment-net', $last_year_data);
    $data[] = ['notes' => 'Property and equipment-net', 'current_year' => $ppe, 'last_year' => $ppe_ly, 'indent' => 1];
    
    $deferred_tax = getTotal('Deferred tax assets', $current_year_data);
    $deferred_tax_ly = getTotal('Deferred tax assets', $last_year_data);
    $data[] = ['notes' => 'Deferred tax assets', 'current_year' => $deferred_tax, 'last_year' => $deferred_tax_ly, 'indent' => 1];
    
    $total_non_current_assets = $ppe + $deferred_tax;
    $total_non_current_assets_ly = $ppe_ly + $deferred_tax_ly;
    $data[] = ['notes' => 'Total Non-current assets', 'current_year' => $total_non_current_assets, 'last_year' => $total_non_current_assets_ly, 'indent' => 2, 'is_calculated' => true];
    
    $total_assets = $total_current_assets + $total_non_current_assets;
    $total_assets_ly = $total_current_assets_ly + $total_non_current_assets_ly;
    $data[] = ['notes' => 'Total Assets', 'current_year' => $total_assets, 'last_year' => $total_assets_ly, 'is_calculated' => true];
    
    // Liabilities and Equity
    $data[] = ['notes' => 'Liabilities and Equity', 'current_year' => '', 'last_year' => '', 'is_header' => true, 'indent' => 0];
    $data[] = ['notes' => 'Current Liabilities', 'current_year' => '', 'last_year' => '', 'is_header' => true, 'indent' => 0];
    
    $payables = getTotal('Trade and other payables', $current_year_data);
    $payables_ly = getTotal('Trade and other payables', $last_year_data);
    $data[] = ['notes' => 'Trade and other payables', 'current_year' => $payables, 'last_year' => $payables_ly, 'indent' => 1];
    
    $dividend_payable = getTotal('Dividend Payable', $current_year_data);
    $dividend_payable_ly = getTotal('Dividend Payable', $last_year_data);
    $data[] = ['notes' => 'Dividend Payable', 'current_year' => $dividend_payable, 'last_year' => $dividend_payable_ly, 'indent' => 1];
    
    $income_tax_payable = getTotal('Income Tax Payable', $current_year_data);
    $income_tax_payable_ly = getTotal('Income Tax Payable', $last_year_data);
    $data[] = ['notes' => 'Income Tax Payable', 'current_year' => $income_tax_payable, 'last_year' => $income_tax_payable_ly, 'indent' => 1];
    
    $total_current_liabilities = $payables + $dividend_payable + $income_tax_payable;
    $total_current_liabilities_ly = $payables_ly + $dividend_payable_ly + $income_tax_payable_ly;
    $data[] = ['notes' => 'Total Current Liabilities', 'current_year' => $total_current_liabilities, 'last_year' => $total_current_liabilities_ly, 'indent' => 2, 'is_calculated' => true];
    
    // Non-current Liabilities
    $data[] = ['notes' => 'Non-current Liabilities', 'current_year' => '', 'last_year' => '', 'is_header' => true, 'indent' => 0];
    
    $loans = getTotal('Loans payable-net of current portion', $current_year_data);
    $loans_ly = getTotal('Loans payable-net of current portion', $last_year_data);
    $data[] = ['notes' => 'Loans payable-net of current portion', 'current_year' => $loans, 'last_year' => $loans_ly, 'indent' => 1];
    
    $retirement = getTotal('Retirement Benefit Obligations', $current_year_data);
    $retirement_ly = getTotal('Retirement Benefit Obligations', $last_year_data);
    $data[] = ['notes' => 'Retirement Benefit Obligations', 'current_year' => $retirement, 'last_year' => $retirement_ly, 'indent' => 1];
    
    $total_non_current_liabilities = $loans + $retirement;
    $total_non_current_liabilities_ly = $loans_ly + $retirement_ly;
    $data[] = ['notes' => 'Total Non-current Liabilities', 'current_year' => $total_non_current_liabilities, 'last_year' => $total_non_current_liabilities_ly, 'indent' => 2, 'is_calculated' => true];
    
    $total_liabilities = $total_current_liabilities + $total_non_current_liabilities;
    $total_liabilities_ly = $total_current_liabilities_ly + $total_non_current_liabilities_ly;
    $data[] = ['notes' => 'Total Liabilities', 'current_year' => $total_liabilities, 'last_year' => $total_liabilities_ly, 'indent' => 2, 'is_calculated' => true];
    
    // Equity
    $data[] = ['notes' => 'Equity', 'current_year' => '', 'last_year' => '', 'is_header' => true, 'indent' => 0];
    
    $share_capital = getTotal('Share capital', $current_year_data);
    $share_capital_ly = getTotal('Share capital', $last_year_data);
    $data[] = ['notes' => 'Share capital', 'current_year' => $share_capital, 'last_year' => $share_capital_ly, 'indent' => 1];
    
    $retained_earnings = getTotal('Retained earnings', $current_year_data);
    $retained_earnings_ly = getTotal('Retained earnings', $last_year_data);
    $data[] = ['notes' => 'Retained earnings', 'current_year' => $retained_earnings, 'last_year' => $retained_earnings_ly, 'indent' => 1];
    
    $total_equity = $share_capital + $retained_earnings;
    $total_equity_ly = $share_capital_ly + $retained_earnings_ly;
    $data[] = ['notes' => 'Total Equity', 'current_year' => $total_equity, 'last_year' => $total_equity_ly, 'indent' => 2, 'is_calculated' => true];
    
    $total_liabilities_and_equity = $total_liabilities + $total_equity;
    $total_liabilities_and_equity_ly = $total_liabilities_ly + $total_equity_ly;
    $data[] = ['notes' => 'Total Liabilities and Equity', 'current_year' => $total_liabilities_and_equity, 'last_year' => $total_liabilities_and_equity_ly, 'is_calculated' => true];
    
    echo json_encode(['data' => $data]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$stmt->close();
$con->close();
?> 
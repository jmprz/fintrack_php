<?php
require_once '../../connection.php';
global $con;

// Set page title and any additional head content
$page_title = "Balance Sheet";

// Get the company ID based on the context
if (isset($_GET['employee_id'])) {
    // Admin viewing an employee's data
    $stmt = $con->prepare("SELECT company_id FROM user_companies WHERE user_id = ?");
    $stmt->bind_param("i", $_GET['employee_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $viewing_company_id = $row['company_id'];
        $_SESSION['viewing_company_id'] = $viewing_company_id;
    }
} else {
    // Employee viewing their own data
    $viewing_company_id = isset($_SESSION['viewing_company_id']) ? $_SESSION['viewing_company_id'] : null;
}

if (!$viewing_company_id) {
    die("No company selected");
}

// If this is an AJAX request, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    try {
        error_log("Processing balance sheet for company ID: " . $viewing_company_id);
        
        $current_year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
        $last_year = $current_year - 1;
        
        // Get current year data
        $current_year_query = "SELECT classification, SUM(ending_balance) as total 
                              FROM trial_balance 
                              WHERE company_id = ? AND year = ?
                              GROUP BY classification";
        $stmt = $con->prepare($current_year_query);
        if (!$stmt) {
            throw new Exception("Failed to prepare current year query: " . $con->error);
        }
        
        $stmt->bind_param("ii", $viewing_company_id, $current_year);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute current year query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $current_year_data = [];
        while ($row = $result->fetch_assoc()) {
            error_log("Current year data for {$row['classification']}: {$row['total']}");
            $current_year_data[$row['classification']] = $row['total'];
        }
        
        // Get last year data
        $last_year_query = "SELECT classification, SUM(ending_balance) as total 
                           FROM trial_balance 
                           WHERE company_id = ? AND year = ?
                           GROUP BY classification";
        $stmt = $con->prepare($last_year_query);
        if (!$stmt) {
            throw new Exception("Failed to prepare last year query: " . $con->error);
        }
        
        $stmt->bind_param("ii", $viewing_company_id, $last_year);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute last year query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $last_year_data = [];
        while ($row = $result->fetch_assoc()) {
            error_log("Last year data for {$row['classification']}: {$row['total']}");
            $last_year_data[$row['classification']] = $row['total'];
        }
        
        // Helper function to get total for a classification
        function getTotal($classification, $data) {
            return isset($data[$classification]) ? $data[$classification] : 0;
        }
        
        // Calculate balance sheet rows
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
        
        $prepayments = getTotal('Prepayments and other current assets', $current_year_data);
        $prepayments_ly = getTotal('Prepayments and other current assets', $last_year_data);
        $data[] = ['notes' => 'Prepayments and other assets', 'current_year' => $prepayments, 'last_year' => $prepayments_ly, 'indent' => 1];
        
        $total_current_assets = $cash + $receivables + $inventories + $prepayments;
        $total_current_assets_ly = $cash_ly + $receivables_ly + $inventories_ly + $prepayments_ly;
        $data[] = ['notes' => 'Total Current Assets', 'current_year' => $total_current_assets, 'last_year' => $total_current_assets_ly, 'indent' => 2, 'is_calculated' => true];
        
        // Non-current Assets
        $data[] = ['notes' => 'Non-current Assets', 'current_year' => '', 'last_year' => '', 'is_header' => true, 'indent' => 0];
        
        $ppe = getTotal('Property and equipment – net', $current_year_data);
        $ppe_ly = getTotal('Property and equipment – net', $last_year_data);
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
        
        $retirement = getTotal('RETIREMENT BENEFIT OBLIGATIONS', $current_year_data);
        $retirement_ly = getTotal('RETIREMENT BENEFIT OBLIGATIONS', $last_year_data);
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
        exit();
    } catch (Exception $e) {
        error_log("Error in balance sheet: " . $e->getMessage());
        error_log("Company ID: " . $viewing_company_id);
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

$additional_head = '
<link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<style>
    .negative-amount { color: red; }
    .indent-1 { padding-left: 2rem !important; }
    .indent-2 { padding-left: 4rem !important; }
    .header-row { 
        font-weight: bold;
        color: #374151;
        padding-top: 1rem !important;
    }
    .calculated-row {
        font-weight: 600;
        border-top: 1px solid #e5e7eb;
    }
    #balanceSheetTable {
        border-collapse: collapse;
        width: 100%;
    }
    #balanceSheetTable td, #balanceSheetTable th {
        border: none;
        padding: 8px;
    }
    .dataTables_filter, .dataTables_info, .dataTables_length {
        display: none !important;
    }
</style>
';

// Start output buffering
ob_start();
?>

<!-- Page specific content -->
<div id="printableArea" class="bg-white rounded-lg shadow-md p-6">
    <div class="text-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Balance Sheet</h2>
        <?php if (isset($_SESSION['viewing_company_name'])): ?>
            <p class="text-lg text-gray-600 mt-1">for <?php echo htmlspecialchars($_SESSION['viewing_company_name']); ?></p>
        <?php endif; ?>
    </div>
            
    <div class="flex flex-wrap gap-4 justify-between items-center mb-6">
        <!-- Year Selector -->
        <div class="flex gap-4">
            <select id="yearSelect" class="rounded border p-2">
                <?php
                $current_year = date('Y');
                $start_year = 2020;
                $end_year = $current_year + 1;
                for ($year = $start_year; $year <= $end_year; $year++) {
                    $selected = $year === $current_year ? 'selected' : '';
                    echo "<option value='$year' $selected>$year</option>";
                }
                ?>
            </select>
        </div>
    </div>
            
    <!-- Balance Sheet Table -->
    <div class="overflow-x-auto">
        <table id="balanceSheetTable" class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase" id="currentYearHeader">Current Year</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase" id="lastYearHeader">Past Year</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data will be populated by JavaScript -->
            </tbody>
        </table>
    </div>
</div>

<?php
// Get the buffered content
$content = ob_get_clean();

// Additional scripts
$additional_scripts = '
$(document).ready(function() {
    function formatAmount(amount) {
        if (amount === null || amount === "") return "-";
        
        const num = parseFloat(amount);
        if (isNaN(num)) return "-";
        
        const formatted = Math.abs(num).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, "$&,");
        return num < 0 ? 
            `<span class="negative-amount">(₱${formatted})</span>` : 
            `₱${formatted}`;
    }

    function updateTableHeaders() {
        const currentYear = $("#yearSelect").val();
        const lastYear = parseInt(currentYear) - 1;
        $("#currentYearHeader").text(currentYear);
        $("#lastYearHeader").text(lastYear);
    }

    // Initialize with empty data first
    var balanceSheetTable = $("#balanceSheetTable").DataTable({
        processing: true,
        serverSide: false,
        paging: false,
        searching: false,
        ordering: false,
        info: false,
        data: [], // Start with empty data
        columns: [
            { 
                data: "notes",
                className: function(data, type, row) {
                    if (!row) return "";
                    let classes = ["px-4", "py-2", "text-sm"];
                    if (row.is_header) classes.push("header-row");
                    if (row.indent === 1) classes.push("indent-1");
                    if (row.indent === 2) classes.push("indent-2");
                    if (row.is_calculated) classes.push("calculated-row");
                    return classes.join(" ");
                },
                 render: function(data, type, row) {
                        if (!row || !data) return "";
                        if (type === "display") {
                            let content = row.is_calculated ? 
                                `<span class="font-bold">${data}</span>` : 
                                data;
                            return content;
                        }
                        return data;
                    }
            },
            { 
                data: "current_year",
                className: "text-right px-4 py-2 text-sm",
                render: function(data, type, row) {
                    return formatAmount(data);
                }
            },
            { 
                data: "last_year",
                className: "text-right px-4 py-2 text-sm",
                render: function(data, type, row) {
                    return formatAmount(data);
                }
            }
        ],
        drawCallback: function() {
            updateTableHeaders();
        }
    });

    // Function to load data
    function loadBalanceSheetData() {
        $.ajax({
            url: window.location.href,
            type: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            },
            data: {
                year: $("#yearSelect").val()
            },
            success: function(response) {
                if (response.error) {
                    console.error("Server error:", response.error);
                    return;
                }
                if (response.data) {
                    balanceSheetTable.clear().rows.add(response.data).draw();
                }
            },
            error: function(xhr, error, thrown) {
                console.error("AJAX error:", error, thrown);
            }
        });
    }

    // Year selection change handler
    $("#yearSelect").change(function() {
        loadBalanceSheetData();
    });

    // Initial data load
    loadBalanceSheetData();
    updateTableHeaders();
});
';

// Include the layout
require_once 'employee_view_layout.php';
?> 
<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if company is selected
if (!isset($_SESSION['selected_company_id'])) {
    header("Location: select_company_message.php");
    exit();
}

$company_id = $_SESSION['selected_company_id'];
$current_year = isset($_GET['year']) ? intval($_GET['year']) : 2025;
$last_year = $current_year - 1;

// If this is an AJAX request, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    try {
        // For debugging
        error_log("Processing AJAX request for year: " . $current_year);
        error_log("Company ID: " . $company_id);
        
        // Get current year data
        $current_year_query = "SELECT classification, SUM(ending_balance) as total 
                              FROM trial_balance 
                              WHERE company_id = ? AND year = ?
                              GROUP BY classification";
        $stmt = $con->prepare($current_year_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $con->error);
        }
        
        $stmt->bind_param("ii", $company_id, $current_year);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $current_year_data = [];
        while ($row = $result->fetch_assoc()) {
            $current_year_data[$row['classification']] = $row['total'];
        }
        
        error_log("Current year data: " . json_encode($current_year_data));
        
        // Get last year data with error checking
        $last_year_query = "SELECT classification, SUM(ending_balance) as total 
                           FROM trial_balance 
                           WHERE company_id = ? AND year = ?
                           GROUP BY classification";
        $stmt = $con->prepare($last_year_query);
        if (!$stmt) {
            throw new Exception("Prepare failed for last year query: " . $con->error);
        }
        
        $stmt->bind_param("ii", $company_id, $last_year);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for last year query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $last_year_data = [];
        while ($row = $result->fetch_assoc()) {
            $last_year_data[$row['classification']] = $row['total'];
        }
        
        error_log("Last year data: " . json_encode($last_year_data));
        
        // Helper function to get total for a classification
        function getTotal($classification, $data) {
            return isset($data[$classification . ' Total']) ? $data[$classification . ' Total'] : 
                   (isset($data[$classification]) ? $data[$classification] : 0);
        }
        
        // Calculate balance sheet rows
        $data = [];
        
        // Assets
        $data[] = ['notes' => 'Assets', 'is_header' => true, 'indent' => 0];
        $data[] = ['notes' => 'Current Assets', 'is_header' => true, 'indent' => 0];
        
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
        $data[] = ['notes' => 'Non-current Assets', 'is_header' => true, 'indent' => 0];
        
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
        $data[] = ['notes' => 'Liabilities and Equity', 'is_header' => true, 'indent' => 0];
        $data[] = ['notes' => 'Current Liabilities', 'is_header' => true, 'indent' => 0];
        
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
        $data[] = ['notes' => 'Non-current Liabilities', 'is_header' => true, 'indent' => 0];
        
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
        $data[] = ['notes' => 'Equity', 'is_header' => true, 'indent' => 0];
        
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
        
        $response = ['data' => $data];
        error_log("Sending response: " . json_encode($response));
        echo json_encode($response);
    } catch (Exception $e) {
        error_log("Error in balance sheet: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/x-icon" href="img/favicon.ico" />
    <title>FinTrack | Balance Sheet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <style>
        .negative-amount { color: red; }
        .calculated-row { font-weight: bold; }
        .indent-1 { padding-left: 2.5rem !important; }
        .indent-2 { padding-left: 4rem !important; }
        .section-header { 
            font-weight: bold;
            background-color: #f3f4f6;
        }
        .balance-sheet-table td {
            padding: 0.75rem 1.5rem;
            white-space: nowrap;
        }
        .balance-sheet-table th {
            padding: 0.75rem 1.5rem;
            white-space: nowrap;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="w-64 bg-white shadow-md fixed inset-y-0 left-0 transform -translate-x-full md:translate-x-0 transition-transform duration-200 z-50">
            <div class="items-center border-b p-4">
                <img class="w-96" src="img/eclick.png" alt="Logo" />
            </div>      
            <nav class="p-4 mb-[6rem]">
                <ul class="space-y-2 text-gray-700">
                    <li><a href="expenses.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Expenses</a></li>
                    <li><a href="sales.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Sales</a></li>
                    <li><a href="trial_balance.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Trial Balance</a></li>
                    <li><a href="income_statements.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Income Statements</a></li>
                    <li><a href="balance_sheet.php" class="block py-2 px-3 rounded bg-[#e4fbeaff] text-[#1bb34cff] font-semibold">Balance Sheet</a></li>
                    <li><a href="profile.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Profile</a></li>
                </ul>
            </nav>
            <a href="logout.php" class="block py-2 px-8 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Logout</a>
        </aside>

        <!-- Overlay -->
        <div id="overlay" class="fixed inset-0 bg-black bg-opacity-40 hidden z-40 md:hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 p-6 md:ml-64">
            <header class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-5xl font-semibold text-gray-800">Balance Sheet</h1>
                    <?php if (isset($_SESSION['selected_company_name'])): ?>
                        <p class="text-lg text-gray-600 mt-2">for <?php echo htmlspecialchars($_SESSION['selected_company_name']); ?></p>
                    <?php endif; ?>
                </div>
                <button id="menuBtn" class="md:hidden px-4 py-2 bg-blue-200 text-white rounded">
                    <div class="text-2xl font-bold text-blue-500">☰</div>
                </button>
            </header>

            <!-- Controls Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-wrap gap-4 justify-between items-center">
                    <!-- Year Selector -->
                    <div class="flex gap-4">
                        <select id="yearSelect" class="rounded border p-2">
                            <?php
                            $start_year = 2020;
                            $end_year = date('Y') + 1;
                            for ($year = $start_year; $year <= $end_year; $year++) {
                                $selected = $year === $current_year ? 'selected' : '';
                                echo "<option value='$year' $selected>$year</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Balance Sheet Table -->
            <div class="overflow-x-auto bg-white rounded shadow-md p-4">
                <table id="balanceSheetTable" class="min-w-full text-sm balance-sheet-table">
                    <thead class="bg-gray-200 text-gray-700 uppercase">
                        <tr>
                            <th class="text-left w-1/2">Notes</th>
                            <th class="text-right w-1/4" id="currentYearHeader"></th>
                            <th class="text-right w-1/4" id="lastYearHeader"></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        menuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        });

        function updateTableHeaders() {
            const currentYear = $('#yearSelect').val();
            const lastYear = parseInt(currentYear) - 1;
            $('#currentYearHeader').text(currentYear);
            $('#lastYearHeader').text(lastYear);
        }

        function formatAmount(amount) {
            if (amount === null || amount === '') return '-';
            
            const num = parseFloat(amount);
            if (isNaN(num)) return '-';
            
            const formatted = Math.abs(num).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            return num < 0 ? 
                `<span class="negative-amount">(₱${formatted})</span>` : 
                `₱${formatted}`;
        }

        // Initialize DataTable
        const table = $('#balanceSheetTable').DataTable({
            processing: true,
            serverSide: false,
            paging: false,
            searching: false,
            ordering: false,
            info: false,
            ajax: {
                url: window.location.href,
                type: 'POST',
                data: function(d) {
                    d.year = $('#yearSelect').val();
                },
                error: function(xhr, error, thrown) {
                    console.error('Ajax error:', error);
                    console.error('Server response:', xhr.responseText);
                },
                dataSrc: function(json) {
                    console.log('Received data:', json);
                    if (json.error) {
                        console.error('Server error:', json.error);
                        return [];
                    }
                    return json.data || [];
                }
            },
            columns: [
                { 
                    data: 'notes',
                    className: function(data, type, row) {
                        if (!row) return '';
                        
                        let classes = '';
                        if (row.indent === 1) classes += ' indent-1';
                        if (row.indent === 2) classes += ' indent-2';
                        if (row.is_header) classes += ' section-header';
                        return classes;
                    },
                    render: function(data, type, row) {
                        if (!row || !data) return '';
                        if (type === 'display') {
                            let content = row.is_calculated ? 
                                `<span class="font-bold">${data}</span>` : 
                                data;
                            return content;
                        }
                        return data;
                    }
                },
                { 
                    data: 'current_year',
                    className: 'text-right',
                    render: function(data, type, row) {
                        if (!row || data === undefined) return '';
                        if (type === 'display') {
                            return row.is_header ? '' : formatAmount(data);
                        }
                        return data;
                    }
                },
                { 
                    data: 'last_year',
                    className: 'text-right',
                    render: function(data, type, row) {
                        if (!row || data === undefined) return '';
                        if (type === 'display') {
                            return row.is_header ? '' : formatAmount(data);
                        }
                        return data;
                    }
                }
            ],
            drawCallback: function(settings) {
                console.log('Table drawn with settings:', settings);
                updateTableHeaders();
            },
            language: {
                emptyTable: "Loading data..."
            }
        });

        // Year selection with error handling
        $('#yearSelect').on('change', function() {
            try {
                table.ajax.reload();
            } catch (e) {
                console.error('Error reloading table:', e);
            }
        });

        // Initial header update
        updateTableHeaders();

        // Initial table load with error handling
        try {
            table.ajax.reload();
        } catch (e) {
            console.error('Error on initial table load:', e);
        }
    </script>
</body>
</html>
